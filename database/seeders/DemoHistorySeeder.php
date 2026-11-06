<?php

namespace Database\Seeders;

use App\Enums\CashShiftStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethodType;
use App\Enums\RepairStatus;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\CashShift;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Models\RepairJob;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\NumberGenerator;
use App\Services\ProductService;
use App\Services\Settings;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Realistic 30-day history for demos: more products, customers, daily sales,
 * purchases, repairs and expenses. Adds data only (never deletes).
 *
 *   php artisan db:seed --class=DemoHistorySeeder
 */
class DemoHistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(ProductService $products, StockService $stock, NumberGenerator $numbers, Settings $settings): void
    {
        if ($settings->get('demo_history_seeded')) {
            $this->command?->warn('Demo history already added.');

            return;
        }

        mt_srand(2026);
        Model::unguard();
        $branch = Branch::query()->orderBy('id')->firstOrFail();
        $admin = User::query()->whereHas('roles', fn ($query) => $query->where('name', 'admin'))->first();
        Auth::login($admin);

        $this->products($products);
        $customers = $this->customers();
        $suppliers = $this->suppliers();

        $units = ProductUnit::query()->with('product')->where('is_base', true)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))->get();
        $methods = PaymentMethod::query()->where('is_active', true)->get();
        $cash = $methods->firstWhere('type', PaymentMethodType::Cash);

        // Make sure there is stock to sell.
        foreach ($units as $unit) {
            if ($stock->quantity($branch, $unit->product) < 40) {
                $stock->move($branch, $unit->product, 60, StockMovementType::Opening, null, null, __('Opening stock'));
            }
        }

        for ($daysAgo = 29; $daysAgo >= 1; $daysAgo--) {
            $day = today()->subDays($daysAgo);
            $shift = CashShift::query()->create([
                'branch_id' => $branch->id, 'user_id' => $admin->id, 'status' => CashShiftStatus::Closed,
                'opened_at' => $day->copy()->setTime(10, 0), 'opening_cash' => 2000,
            ]);
            $cashIn = 0.0;

            $salesToday = mt_rand(4, $day->isFriday() ? 14 : 10);

            for ($i = 0; $i < $salesToday; $i++) {
                $at = $day->copy()->setTime(mt_rand(10, 20), mt_rand(0, 59));
                $lines = $units->random(mt_rand(1, min(4, $units->count())));
                $total = 0.0;

                $sale = Sale::query()->create([
                    'branch_id' => $branch->id, 'invoice_no' => $numbers->next($branch, 'invoice'), 'status' => SaleStatus::Completed,
                    'customer_id' => mt_rand(1, 10) <= 4 ? $customers->random()->id : null, 'user_id' => $admin->id,
                    'cash_shift_id' => $shift->id, 'sold_at' => $at, 'subtotal' => 0, 'total' => 0, 'paid_amount' => 0,
                    'created_at' => $at, 'updated_at' => $at,
                ]);

                foreach ($lines as $unit) {
                    $quantity = mt_rand(1, 10) <= 8 ? 1 : mt_rand(2, 3);
                    $lineTotal = round($quantity * (float) $unit->selling_price, 2);
                    $total += $lineTotal;

                    $sale->items()->create([
                        'product_id' => $unit->product_id, 'product_unit_id' => $unit->id, 'product_name' => $unit->product->name,
                        'unit_name' => $unit->name, 'quantity' => $quantity, 'unit_quantity' => 1, 'base_quantity' => $quantity,
                        'unit_price' => $unit->selling_price, 'unit_cost' => $unit->product->cost_price, 'line_total' => $lineTotal,
                        'warranty_days' => $unit->product->warranty_days,
                    ]);

                    $movement = $stock->move($branch, $unit->product, -$quantity, StockMovementType::Sale, $sale);
                    StockMovement::query()->whereKey($movement->id)->update(['created_at' => $at]);
                }

                $sale->update(['subtotal' => $total, 'total' => $total, 'paid_amount' => $total]);
                $method = mt_rand(1, 10) <= 6 ? $cash : $methods->random();

                Payment::query()->create([
                    'branch_id' => $branch->id, 'payable_type' => 'sale', 'payable_id' => $sale->id, 'payment_method_id' => $method->id,
                    'direction' => PaymentDirection::In, 'amount' => $total, 'cash_shift_id' => $method->type === PaymentMethodType::Cash ? $shift->id : null,
                    'user_id' => $admin->id, 'paid_at' => $at, 'created_at' => $at, 'updated_at' => $at,
                ]);

                if ($method->type === PaymentMethodType::Cash) {
                    $cashIn += $total;
                }
            }

            $expected = 2000 + $cashIn;
            $counted = $expected + (mt_rand(1, 10) <= 8 ? 0 : -mt_rand(10, 100));
            $shift->update([
                'closed_at' => $day->copy()->setTime(21, 30), 'expected_cash' => $expected,
                'counted_cash' => $counted, 'difference' => $counted - $expected,
            ]);

            // Purchases about twice a week.
            if ($daysAgo % 4 === 0) {
                $this->purchase($branch, $suppliers->random(), $units->random(mt_rand(3, 6)), $cash, $day, $numbers, $stock);
            }
        }

        $this->repairs($branch, $customers, $methods, $numbers, $units);
        $this->expenses($branch, $cash);

        Model::reguard();
        $settings->set('demo_history_seeded', true);
        $this->command?->info('Demo history added.');
    }

    private function products(ProductService $products): void
    {
        $piece = Unit::query()->where('short_name', 'pcs')->firstOrFail();
        $category = fn (string $name) => Category::query()->where('name', $name)->value('id') ?? Category::query()->value('id');
        $brand = fn (string $name) => Brand::query()->firstOrCreate(['name' => $name])->id;
        $items = [
            ['25W Super Fast Charger', 'Wall chargers', 'Samsung', 650, 1050, 180], ['33W Dual Port Charger', 'Wall chargers', 'Xiaomi', 520, 850, 180],
            ['Car Charger 2-Port', 'Car chargers', 'Baseus', 280, 550, 90], ['15W Magnetic Wireless Charger', 'Wireless chargers', 'Anker', 900, 1450, 180],
            ['Type-C to Type-C Cable 2m', 'Type-C', 'Anker', 220, 420, 30], ['Braided Type-C Cable 1m', 'Type-C', 'Ugreen', 160, 320, 30],
            ['Micro USB Cable 1m', 'Micro USB', 'Remax', 60, 150, 30], ['iPhone 13 Silicone Case', 'Covers & Cases', null, 120, 350, null],
            ['Redmi Note 13 Back Cover', 'Covers & Cases', null, 70, 200, null], ['Samsung A25 Flip Cover', 'Covers & Cases', null, 150, 400, null],
            ['Privacy Glass iPhone 14', 'Screen Protectors', null, 60, 250, null], ['Camera Lens Protector', 'Screen Protectors', null, 30, 120, null],
            ['TWS Earbuds Pro', 'Earphones & Headphones', 'Realme', 1100, 1750, 180], ['Neckband Bluetooth Earphone', 'Earphones & Headphones', 'Oraimo', 650, 1100, 90],
            ['Gaming Headphone', 'Earphones & Headphones', 'Havit', 950, 1600, 180], ['20000mAh Power Bank', 'Power Banks', 'Anker', 1900, 2800, 365],
            ['5000mAh Mini Power Bank', 'Power Banks', 'Xiaomi', 750, 1150, 180], ['Redmi 13C Display', 'Displays', 'Xiaomi', 1400, 2300, 30],
            ['Samsung A05 Display', 'Displays', 'Samsung', 1600, 2600, 30], ['iPhone 11 Battery', 'Batteries', null, 1100, 2200, 90],
            ['Samsung A12 Battery', 'Batteries', 'Samsung', 550, 1100, 90], ['Type-C Charging Board', 'Charging Ports', null, 120, 450, null],
            ['Phone Holder Stand', 'Accessories', null, 90, 250, null], ['OTG Adapter Type-C', 'Accessories', 'Ugreen', 60, 180, null],
            ['32GB Memory Card', 'Electronics', 'SanDisk', 320, 550, 365], ['64GB Pendrive', 'Electronics', 'SanDisk', 420, 700, 365],
            ['Smart Watch Strap', 'Accessories', null, 80, 250, null], ['Ring Light 10 inch', 'Electronics', null, 650, 1100, 30],
        ];

        $branch = Branch::query()->orderBy('id')->first();

        foreach ($items as [$name, $categoryName, $brandName, $cost, $price, $warranty]) {
            if (Product::query()->where('name', $name)->exists()) {
                continue;
            }

            $products->save(null, [
                'name' => $name, 'category_id' => $category($categoryName), 'brand_id' => $brandName ? $brand($brandName) : null,
                'unit_id' => $piece->id, 'cost_price' => $cost, 'reorder_level' => mt_rand(3, 10), 'warranty_days' => $warranty,
            ], [[
                'name' => $piece->name, 'name_bn' => $piece->name_bn, 'quantity' => 1, 'selling_price' => $price, 'is_base' => true,
                'barcodes' => [['code' => '69'.str_pad((string) mt_rand(1, 99999999999), 11, '0', STR_PAD_LEFT)]],
            ]], $branch, mt_rand(20, 120));
        }
    }

    /**
     * @return Collection<int, Customer>
     */
    private function customers()
    {
        $names = ['Rahim Uddin', 'Karim Hossain', 'Nusrat Jahan', 'Tanvir Ahmed', 'Sadia Islam', 'Mehedi Hasan', 'Farhana Akter', 'Arif Chowdhury',
            'Shamim Reza', 'Jannatul Ferdous', 'Rakib Hasan', 'Sumaiya Khan', 'Imran Hossain', 'Tasnim Rahman', 'Habib Mia', 'Rumana Begum',
            'Sohel Rana', 'Nadia Sultana', 'Jahid Hasan', 'Mim Akter', 'Asif Iqbal', 'Lamia Haque', 'Fahim Shahriar', 'Ruma Das', 'Sabbir Ahmed'];

        foreach ($names as $index => $name) {
            $customer = Customer::query()->firstOrCreate(['phone' => '0171'.str_pad((string) (1000000 + $index * 7319), 7, '0', STR_PAD_LEFT)], ['name' => $name]);
            $customer->forceFill(['created_at' => now()->subDays(mt_rand(1, 60))])->saveQuietly();
        }

        return Customer::query()->get();
    }

    /**
     * @return Collection<int, Supplier>
     */
    private function suppliers()
    {
        foreach ([['Dhaka Mobile Wholesale', 'Stadium Market'], ['Gulistan Accessories', 'Gulistan'], ['Tech Parts BD', 'Motijheel'], ['Elephant Road Electronics', 'Elephant Road']] as [$name, $company]) {
            Supplier::query()->firstOrCreate(['name' => $name], ['company' => $company, 'phone' => '0181'.mt_rand(1000000, 9999999)]);
        }

        return Supplier::query()->get();
    }

    private function purchase(Branch $branch, Supplier $supplier, $units, PaymentMethod $cash, Carbon $day, NumberGenerator $numbers, StockService $stock): void
    {
        $at = $day->copy()->setTime(12, 0);
        $purchase = Purchase::query()->create([
            'branch_id' => $branch->id, 'supplier_id' => $supplier->id, 'reference_no' => $numbers->next($branch, 'purchase'),
            'supplier_invoice_no' => 'S-'.mt_rand(1000, 9999), 'purchase_date' => $day->toDateString(), 'subtotal' => 0, 'total' => 0,
            'user_id' => Auth::id(), 'created_at' => $at, 'updated_at' => $at,
        ]);
        $total = 0.0;

        foreach ($units as $unit) {
            $quantity = mt_rand(10, 40);
            $cost = (float) $unit->product->cost_price;
            $total += $quantity * $cost;
            $purchase->items()->create([
                'product_id' => $unit->product_id, 'product_unit_id' => $unit->id, 'quantity' => $quantity, 'unit_quantity' => 1,
                'base_quantity' => $quantity, 'unit_cost' => $cost, 'line_total' => $quantity * $cost,
            ]);
            $movement = $stock->move($branch, $unit->product, $quantity, StockMovementType::Purchase, $purchase, $cost);
            StockMovement::query()->whereKey($movement->id)->update(['created_at' => $at]);
        }

        $purchase->update(['subtotal' => $total, 'total' => $total]);
        Payment::query()->create([
            'branch_id' => $branch->id, 'payable_type' => 'purchase', 'payable_id' => $purchase->id, 'payment_method_id' => $cash->id,
            'direction' => PaymentDirection::Out, 'amount' => $total, 'user_id' => Auth::id(), 'paid_at' => $at, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    private function repairs(Branch $branch, $customers, $methods, NumberGenerator $numbers, $units): void
    {
        $devices = [['Samsung', 'A15', 'Display broken'], ['Xiaomi', 'Redmi Note 12', 'Not charging'], ['iPhone', '11', 'Battery drains fast'],
            ['Realme', 'C55', 'Speaker not working'], ['Oppo', 'A78', 'Water damage'], ['Vivo', 'Y17s', 'Touch not working'],
            ['Samsung', 'A05', 'Charging port loose'], ['Infinix', 'Hot 40', 'Software problem, stuck on logo'], ['iPhone', 'XR', 'Face ID not working'],
            ['Tecno', 'Spark 20', 'Back glass broken'], ['Xiaomi', 'Redmi 13C', 'Display lines'], ['Samsung', 'A25', 'Camera blurry']];
        $statuses = [RepairStatus::Received, RepairStatus::Received, RepairStatus::Diagnosing, RepairStatus::WaitingForParts, RepairStatus::Repairing,
            RepairStatus::Repairing, RepairStatus::Ready, RepairStatus::Ready, RepairStatus::Delivered, RepairStatus::Delivered, RepairStatus::Delivered, RepairStatus::Cancelled];
        $cash = $methods->firstWhere('type', PaymentMethodType::Cash);

        foreach ($devices as $index => [$brand, $model, $problem]) {
            $status = $statuses[$index];
            $received = now()->subDays(mt_rand(1, 20))->setTime(mt_rand(10, 19), mt_rand(0, 59));
            $charge = [300, 500, 800, 1200, 1500][mt_rand(0, 4)];
            $delivered = $status === RepairStatus::Delivered ? $received->copy()->addDays(mt_rand(1, 3)) : null;

            $job = RepairJob::query()->create([
                'branch_id' => $branch->id, 'job_no' => $numbers->next($branch, 'repair'), 'status' => $status,
                'customer_id' => $customers->random()->id, 'device_type' => 'phone', 'device_brand' => $brand, 'device_model' => $model,
                'imei_serial' => '35'.mt_rand(1000000000000, 9999999999999), 'accessories' => mt_rand(0, 1) ? ['charger'] : [],
                'problem' => $problem, 'estimated_cost' => $charge, 'estimated_delivery_at' => $received->copy()->addDays(mt_rand(1, 4))->setTime(18, 0),
                'service_charge' => $charge, 'total' => $charge, 'paid_amount' => $delivered ? $charge : 0, 'warranty_days' => 7,
                'warranty_until' => $delivered?->copy()->addDays(7), 'delivered_at' => $delivered, 'received_by' => Auth::id(),
                'created_at' => $received, 'updated_at' => $delivered ?? $received,
            ]);
            $job->statusHistories()->create(['status' => RepairStatus::Received, 'user_id' => Auth::id(), 'created_at' => $received]);

            if ($status !== RepairStatus::Received) {
                $job->statusHistories()->create(['status' => $status, 'user_id' => Auth::id(), 'created_at' => $delivered ?? $received->copy()->addHours(5)]);
            }

            if ($delivered) {
                Payment::query()->create([
                    'branch_id' => $branch->id, 'payable_type' => 'repair_job', 'payable_id' => $job->id, 'payment_method_id' => $cash->id,
                    'direction' => PaymentDirection::In, 'amount' => $charge, 'user_id' => Auth::id(), 'paid_at' => $delivered, 'created_at' => $delivered, 'updated_at' => $delivered,
                ]);
            }
        }
    }

    private function expenses(Branch $branch, PaymentMethod $cash): void
    {
        $plan = [['Rent', 15000, 1], ['Electricity', 2400, 3], ['Internet', 1200, 5], ['Salary', 12000, 2], ['Food & Tea', 150, 0], ['Transport', 300, 0]];

        foreach ($plan as [$categoryName, $amount, $dayOfMonth]) {
            $category = ExpenseCategory::query()->where('name', $categoryName)->first() ?? ExpenseCategory::query()->first();
            $dates = $dayOfMonth ? [today()->startOfMonth()->addDays($dayOfMonth - 1)] : collect(range(1, 8))->map(fn () => today()->subDays(mt_rand(1, 28)))->all();

            foreach ($dates as $date) {
                if ($date->isFuture()) {
                    $date = today();
                }

                $expense = Expense::query()->create([
                    'branch_id' => $branch->id, 'expense_category_id' => $category->id, 'expense_date' => $date->toDateString(),
                    'amount' => $dayOfMonth ? $amount : mt_rand($amount / 2, $amount * 2), 'note' => $categoryName, 'user_id' => Auth::id(),
                ]);
                Payment::query()->create([
                    'branch_id' => $branch->id, 'payable_type' => 'expense', 'payable_id' => $expense->id, 'payment_method_id' => $cash->id,
                    'direction' => PaymentDirection::Out, 'amount' => $expense->amount, 'user_id' => Auth::id(), 'paid_at' => $date->copy()->setTime(13, 0),
                ]);
            }
        }
    }
}
