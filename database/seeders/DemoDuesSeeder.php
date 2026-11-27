<?php

namespace Database\Seeders;

use App\Enums\PaymentDirection;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\NumberGenerator;
use App\Services\Settings;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Demo credit (due) sales for the due reports: regular customers who buy on due,
 * part payments, later due collections with money receipts, and dues of every age.
 * Adds data only (never deletes) and runs once.
 *
 *   php artisan db:seed --class=DemoDuesSeeder
 */
class DemoDuesSeeder extends Seeder
{
    /** Regular buyers who take goods on due, with their credit limit. */
    private const CUSTOMERS = [
        ['Rupayan Builders (site: Mirpur 11)', '01711-200301', 'Plot 7, Road 3, Mirpur 11', 500000],
        ['Hasan Construction', '01819-200302', 'Shewrapara, Mirpur', 300000],
        ['Nusrat Jahan (house build)', '01911-200303', 'House 12, Road 5, Uttara', 150000],
        ['Bismillah Developers', '01552-200304', 'Pallabi, Mirpur 12', 400000],
        ['Rafiq Uddin (Electrician)', '01677-200305', 'Mohammadpur', 50000],
        ['Shapla Interiors', '01799-200306', 'Banani Road 11', 200000],
        ['Tanvir Hossain (renovation)', '01313-200307', 'Banani DOHS', null],
        ['Green Line Properties', '01844-200308', 'Bashundhara R/A, Block C', 600000],
    ];

    /**
     * Run the database seeds.
     */
    public function run(StockService $stock, NumberGenerator $numbers, Settings $settings): void
    {
        if ($settings->get('demo_dues_seeded')) {
            $this->command?->warn('Demo dues already added.');

            return;
        }

        mt_srand(2611);
        Model::unguard();
        $branch = Branch::query()->orderBy('id')->firstOrFail();
        $admin = User::query()->whereHas('roles', fn ($query) => $query->where('name', 'admin'))->firstOrFail();
        Auth::login($admin);

        $units = ProductUnit::query()->with('product.unit')->where('is_base', true)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))->get();

        if ($units->isEmpty()) {
            $this->command?->warn('Add products first (ShopSetupSeeder / DemoHistorySeeder).');

            return;
        }

        $methods = PaymentMethod::query()->where('is_active', true)->get();
        $cash = $methods->firstWhere('type', PaymentMethodType::Cash) ?? $methods->first();
        $dueDays = (int) $settings->get('credit_due_days', $branch->id);

        foreach ($units as $unit) {
            $needed = DemoDataSeeder::demoQuantity($unit, purchase: true);

            if ($stock->quantity($branch, $unit->product) < $needed) {
                $stock->move($branch, $unit->product, $needed, StockMovementType::Opening, null, null, __('Opening stock'));
            }
        }

        foreach (self::CUSTOMERS as [$name, $phone, $address, $limit]) {
            $customer = Customer::query()->firstOrCreate(['phone' => $phone], ['name' => $name, 'address' => $address]);
            $customer->update(['credit_limit' => $limit]);
            $invoices = mt_rand(3, 7);

            for ($i = 0; $i < $invoices; $i++) {
                $at = today()->subDays(mt_rand(2, 120))->setTime(mt_rand(10, 20), mt_rand(0, 59));
                $sale = $this->creditSale($branch, $admin, $customer, $units, $numbers, $stock, $at, $dueDays);

                /** Paid at the counter: nothing, or part of the bill. */
                $paidNow = mt_rand(1, 10) <= 4 ? 0.0 : round((float) $sale->total * mt_rand(2, 6) / 10, -1);

                if ($paidNow > 0) {
                    $method = mt_rand(1, 10) <= 7 ? $cash : $methods->random();
                    Payment::query()->create([
                        'branch_id' => $branch->id, 'payable_type' => 'sale', 'payable_id' => $sale->id, 'payment_method_id' => $method->id,
                        'direction' => PaymentDirection::In, 'amount' => $paidNow, 'user_id' => $admin->id,
                        'paid_at' => $at, 'created_at' => $at, 'updated_at' => $at,
                    ]);
                }

                $due = round((float) $sale->total - $paidNow, 2);
                $sale->update(['paid_amount' => $paidNow, 'due_amount' => $due, 'payment_status' => PaymentStatus::for((float) $sale->total, $due)]);
            }

            $this->collections($branch, $admin, $customer, $methods, $cash, $numbers);
        }

        $settings->set('demo_dues_seeded', true);
        Model::reguard();
        $this->command?->info('Demo dues added: '.count(self::CUSTOMERS).' credit customers with invoices, part payments and collections.');
    }

    /**
     * @param  Collection<int, ProductUnit>  $units
     */
    private function creditSale(Branch $branch, User $admin, Customer $customer, $units, NumberGenerator $numbers, StockService $stock, Carbon $at, int $dueDays): Sale
    {
        $sale = Sale::query()->create([
            'branch_id' => $branch->id, 'invoice_no' => $numbers->next($branch, 'invoice'), 'status' => SaleStatus::Completed,
            'customer_id' => $customer->id, 'user_id' => $admin->id, 'sold_at' => $at, 'subtotal' => 0, 'total' => 0,
            'paid_amount' => 0, 'due_date' => $at->copy()->addDays($dueDays)->toDateString(),
            'note' => __('Sold on due'), 'created_at' => $at, 'updated_at' => $at,
        ]);
        $total = 0.0;

        foreach ($units->random(mt_rand(1, min(5, $units->count()))) as $unit) {
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

        /** Round to the nearest 10 like many shops do on credit bills. */
        $rounded = round($total, -1);
        $sale->update(['subtotal' => $total, 'rounding_amount' => round($rounded - $total, 2), 'total' => $rounded]);

        return $sale;
    }

    /**
     * Customers come back and pay part of what they owe, oldest invoices first.
     *
     * @param  Collection<int, PaymentMethod>  $methods
     */
    private function collections(Branch $branch, User $admin, Customer $customer, $methods, PaymentMethod $cash, NumberGenerator $numbers): void
    {
        $visits = mt_rand(0, 3);

        for ($visit = 0; $visit < $visits; $visit++) {
            $open = $customer->dueSales()->get();

            if ($open->isEmpty()) {
                return;
            }

            $oldest = $open->first()->sold_at;
            $at = Carbon::parse($oldest)->addDays(mt_rand(5, max(6, (int) $oldest->diffInDays(today()) - 1)))->setTime(mt_rand(11, 19), mt_rand(0, 59));

            if ($at->isFuture()) {
                $at = now()->subHours(mt_rand(2, 30));
            }

            $amount = round(min((float) $open->sum('due_amount'), (float) $open->sum('due_amount') * mt_rand(3, 8) / 10), -1);

            if ($amount <= 0) {
                return;
            }

            $method = mt_rand(1, 10) <= 6 ? $cash : $methods->random();
            $receipt = CustomerPayment::query()->create([
                'branch_id' => $branch->id, 'customer_id' => $customer->id, 'receipt_no' => $numbers->next($branch, 'due_receipt'),
                'payment_method_id' => $method->id, 'amount' => $amount,
                'transaction_ref' => $method->type === PaymentMethodType::Cash ? null : strtoupper(substr(md5((string) mt_rand()), 0, 10)),
                'user_id' => $admin->id, 'paid_at' => $at, 'created_at' => $at, 'updated_at' => $at,
            ]);
            $remaining = $amount;

            foreach ($open as $sale) {
                if ($remaining <= 0 || $sale->sold_at->gt($at)) {
                    continue;
                }

                $part = round(min($remaining, (float) $sale->due_amount), 2);
                Payment::query()->create([
                    'branch_id' => $branch->id, 'payable_type' => 'sale', 'payable_id' => $sale->id, 'customer_payment_id' => $receipt->id,
                    'payment_method_id' => $method->id, 'direction' => PaymentDirection::In, 'amount' => $part,
                    'transaction_ref' => $receipt->transaction_ref, 'user_id' => $admin->id, 'paid_at' => $at, 'created_at' => $at, 'updated_at' => $at,
                ]);
                $sale->setDue((float) $sale->due_amount - $part);
                $remaining = round($remaining - $part, 2);
            }

            if ($remaining > 0) {
                $receipt->update(['amount' => round($amount - $remaining, 2)]);
            }

            if ((float) $receipt->fresh()->amount <= 0) {
                $receipt->delete();
            }
        }
    }
}
