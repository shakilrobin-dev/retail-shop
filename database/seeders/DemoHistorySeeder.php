<?php

namespace Database\Seeders;

use App\Enums\CashShiftStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethodType;
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
use App\Models\Referrer;
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
 * purchases and expenses. Adds data only (never deletes).
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
        $referrers = collect([['Karim Mistri', '01911000000', 10], ['Engr. Habib', '01911000001', 5], ['Sobuj Contractor', '01911000002', null]])
            ->map(fn (array $row) => Referrer::query()->firstOrCreate(['phone' => $row[1]], ['name' => $row[0], 'commission_percent' => $row[2]]));

        $units = ProductUnit::query()->with('product.unit')->where('is_base', true)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))->get();
        $methods = PaymentMethod::query()->where('is_active', true)->get();
        $cash = $methods->firstWhere('type', PaymentMethodType::Cash);

        // Make sure there is stock to sell.
        foreach ($units as $unit) {
            $needed = DemoDataSeeder::demoQuantity($unit, purchase: true);

            if ($stock->quantity($branch, $unit->product) < $needed) {
                $stock->move($branch, $unit->product, $needed, StockMovementType::Opening, null, null, __('Opening stock'));
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
                    'referrer_id' => mt_rand(1, 10) <= 3 ? $referrers->random()->id : null,
                    'cash_shift_id' => $shift->id, 'sold_at' => $at, 'subtotal' => 0, 'total' => 0, 'paid_amount' => 0,
                    'created_at' => $at, 'updated_at' => $at,
                ]);

                foreach ($lines as $unit) {
                    $quantity = DemoDataSeeder::demoQuantity($unit);

                    if ($stock->quantity($branch, $unit->product) < $quantity) {
                        $stock->move($branch, $unit->product, $quantity * 3, StockMovementType::Opening, null, null, __('Opening stock'));
                    }

                    $line = DemoDataSeeder::demoLine($unit, $quantity);
                    $total += $line['line_total'];

                    $sale->items()->create([
                        'product_id' => $unit->product_id, 'product_unit_id' => $unit->id, 'product_name' => $unit->product->name,
                        'unit_name' => $unit->name, 'quantity' => $quantity, 'unit_quantity' => 1, 'base_quantity' => $quantity,
                        'unit_cost' => $unit->product->cost_price, 'warranty_days' => $unit->product->warranty_days,
                    ] + $line);

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
            ['Wash Basin Mixer Tap', 'Taps & Showers', 'Hatim', 1400, 1950, null], ['Hand Shower Set', 'Taps & Showers', 'Hatim', 650, 950, null],
            ['Overhead Shower 8 inch', 'Taps & Showers', 'Stella', 900, 1350, null], ['Commode Seat Cover', 'Commode & Basin', null, 450, 750, null],
            ['Low-Down Commode', 'Commode & Basin', 'Stella', 4200, 5600, null], ['PVC Elbow 1 inch', 'Pipes & Fittings', 'RFL', 12, 20, null],
            ['PVC Tee 1 inch', 'Pipes & Fittings', 'RFL', 15, 25, null], ['GI Pipe 1/2 inch (20 ft)', 'Pipes & Fittings', null, 950, 1200, null],
            ['Water Tank Ball Valve', 'Pipes & Fittings', null, 220, 350, null], ['Socket 2 Pin', 'Switches & Sockets', 'Walton', 55, 85, null],
            ['Socket 3 Pin Universal', 'Switches & Sockets', 'Walton', 90, 140, null], ['Ceiling Fan 56 inch', 'Lights & Fans', 'Walton', 3100, 3900, 730],
            ['LED Tube Light 20W', 'Lights & Fans', 'Transtec', 280, 420, 365], ['Circuit Breaker 63A', 'Circuit Breakers', 'Schneider', 650, 900, 365],
            ['Distribution Board 8 Way', 'Circuit Breakers', 'Schneider', 1200, 1650, null], ['Concrete Nail 2 inch Packet', 'Nails & Screws', null, 60, 95, null],
            ['Wood Screw 1.5 inch Packet', 'Nails & Screws', null, 70, 110, null], ['Anchor Bolt 12mm', 'Nuts & Bolts', null, 25, 40, null],
            ['Hammer 1 kg', 'Tools', 'Stanley', 380, 550, null], ['Measuring Tape 5m', 'Tools', 'Stanley', 220, 350, null],
            ['Trowel 9 inch', 'Tools', null, 150, 240, null], ['Spirit Level 24 inch', 'Tools', 'Stanley', 450, 650, null],
            ['Tiles Grout 1 kg', 'Tiles', 'Mapei', 90, 140, null], ['Tiles Adhesive 20 kg', 'Tiles', 'Mapei', 650, 850, null],
            ['Angle Bar 1.5 inch (20 ft)', 'Angle & Flat Bar', null, 1300, 1550, null], ['Flat Bar 1 inch (20 ft)', 'Angle & Flat Bar', null, 700, 850, null],
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
        foreach ([['BSRM Dealer Point', 'Tejgaon'], ['Shah Cement Distributor', 'Narayanganj'], ['RAK Tiles Showroom', 'Banglamotor'], ['Nawabpur Electric House', 'Nawabpur'], ['Babubazar Sanitary', 'Babubazar']] as [$name, $company]) {
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
            $quantity = DemoDataSeeder::demoQuantity($unit, purchase: true);
            $cost = (float) $unit->product->cost_price;
            $line = DemoDataSeeder::demoLine($unit, $quantity, atCost: true);
            $total += $line['line_total'];
            $purchase->items()->create([
                'product_id' => $unit->product_id, 'product_unit_id' => $unit->id, 'quantity' => $quantity, 'unit_quantity' => 1,
                'base_quantity' => $quantity, 'unit_cost' => $line['unit_price'], 'measure_unit' => $line['measure_unit'],
                'measure_quantity' => $line['measure_quantity'], 'measure_rate' => $line['measure_rate'], 'line_total' => $line['line_total'],
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
