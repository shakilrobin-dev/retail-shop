<?php

namespace Database\Seeders;

use App\Enums\CommissionType;
use App\Enums\PaymentMethodType;
use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Referrer;
use App\Models\Sale;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\QuotationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Full demo for a construction materials shop: products (rods, cement, tiles by sqft,
 * sanitary, electrical, hardware), staff users, a month of sales and purchases,
 * credit customers, references with worked-out commission, and quotations.
 *
 * Start clean with:
 *   php artisan migrate:fresh --seed
 *   php artisan db:seed --class=ConstructionDemoSeeder
 */
class ConstructionDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(CommissionService $commissions, QuotationService $quotations): void
    {
        $this->call([
            DemoDataSeeder::class,
            DemoUsersSeeder::class,
            DemoHistorySeeder::class,
            DemoDuesSeeder::class,
        ]);

        $branch = Branch::query()->orderBy('id')->firstOrFail();
        Auth::login(User::query()->where('username', 'admin')->firstOrFail());

        $this->commissions($commissions, $branch);
        $this->quotations($quotations, $branch);

        $this->command?->info('Construction demo data added.');
    }

    /**
     * Commission worked out for the first half of the month: one paid in part, one paid in full.
     */
    private function commissions(CommissionService $commissions, Branch $branch): void
    {
        $cash = PaymentMethod::query()->where('type', PaymentMethodType::Cash)->firstOrFail();
        $from = today()->subDays(29);
        $to = today()->subDays(15);

        $karim = Referrer::query()->where('phone', '01911000000')->first();
        $habib = Referrer::query()->where('phone', '01911000001')->first();

        if ($karim && $commissions->summary($karim, $from, $to)['profit'] > 0) {
            $settlement = $commissions->settle($branch, $karim, $from, $to, CommissionType::ProfitPercent, 10, __('First half of the month'));
            $commissions->pay($branch, $karim, round((float) $settlement->amount / 2, -1), $cash, today()->subDays(10)->toDateString(), null, false);
        }

        if ($habib && $commissions->summary($habib, $from, $to)['profit'] > 0) {
            $commissions->settle($branch, $habib, $from, $to, CommissionType::Amount, 1500, null, 1500, $cash, false);
        }
    }

    /**
     * A few quotations: open ones, an expired one and one that was sold.
     */
    private function quotations(QuotationService $quotations, Branch $branch): void
    {
        $unit = fn (string $name) => Product::query()->with('units')->where('name', $name)->firstOrFail()->units->firstWhere('is_base', true);
        $tiles = $unit('Mirror Wall Tiles 1.77');
        $floor = $unit('Floor Tiles 24x24 Glossy');
        $cement = $unit('Portland Cement 50kg');
        $rod = $unit('MS Rod 12mm 500W');
        $commode = $unit('Commode One-Piece');

        $plans = [
            // customer, phone, site, days ago, valid days, lines [unit, quantity in base units, price or rate per sqft, measured]
            ['Abdul Karim', '01700100200', 'House 22, Road 4, Mirpur 2', 1, 7, [[$tiles, 283, 115, true], [$floor, 125, 90, true]]],
            ['Rupayan Builders (site: Mirpur 11)', '01711-200301', 'Plot 7, Road 3, Mirpur 11', 3, 15, [[$rod, 2500, 93, false], [$cement, 200, 520, false]]],
            ['Shapla Interiors', '01799-200306', 'Banani Road 11', 20, 7, [[$commode, 4, 9200, false], [$tiles, 150, 118, true]]],
            ['Nusrat Jahan (house build)', '01911-200303', 'House 12, Road 5, Uttara', 8, 10, [[$cement, 60, 525, false], [$floor, 80, 92, true]]],
        ];

        foreach ($plans as $index => [$name, $phone, $site, $daysAgo, $validDays, $lines]) {
            $quotation = $quotations->save($branch, null, [
                'customer_name' => $name,
                'customer_phone' => $phone,
                'customer_address' => $site,
                'quotation_date' => today()->subDays($daysAgo)->toDateString(),
                'valid_until' => today()->subDays($daysAgo)->addDays($validDays)->toDateString(),
                'note' => __('Delivery within 3 days of order. Prices are valid until the date above.'),
            ], array_map(fn (array $line): array => $line[3]
                ? ['product_unit_id' => $line[0]->id, 'quantity' => $line[1], 'unit_price' => 0, 'measure_rate' => $line[2], 'discount_amount' => 0]
                : ['product_unit_id' => $line[0]->id, 'quantity' => $line[1], 'unit_price' => $line[2], 'discount_amount' => 0], $lines));

            $quotation->forceFill(['created_at' => today()->subDays($daysAgo)->setTime(11, 0)])->saveQuietly();

            /** The last one was sold: link it to a sale of that customer, or any recent sale. */
            if ($index === 3) {
                $sale = Sale::query()->whereHas('customer', fn ($query) => $query->where('phone', $phone))->latest('sold_at')->first()
                    ?? Sale::query()->latest('sold_at')->first();
                Quotation::query()->whereKey($quotation->id)->update(['sale_id' => $sale?->id]);
            }
        }
    }
}
