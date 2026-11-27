<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\ProductUnit;
use App\Models\Referrer;
use App\Models\Supplier;
use App\Models\Unit;
use App\Services\ProductService;
use Illuminate\Database\Seeder;

/**
 * Sample construction-material products for trying the system. Not run by default:
 *   php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(ProductService $products): void
    {
        $branch = Branch::query()->orderBy('id')->firstOrFail();
        $unit = fn (string $short) => Unit::query()->where('short_name', $short)->firstOrFail();
        $category = fn (string $name) => Category::query()->where('name', $name)->firstOrFail()->id;
        $brand = fn (string $name) => Brand::query()->firstOrCreate(['name' => $name])->id;

        Supplier::query()->firstOrCreate(['name' => 'Dhaka Building Materials'], ['phone' => '01711000000', 'company' => 'Babubazar']);
        Customer::query()->firstOrCreate(['phone' => '01811000000'], ['name' => 'Rahim Uddin']);
        Referrer::query()->firstOrCreate(['phone' => '01911000000'], ['name' => 'Karim Mistri', 'commission_percent' => 10]);

        $items = [
            // name, name_bn, category, brand, base unit, cost per base unit, price per base unit, stock, barcode, pack [name, qty, price], measure [unit, per piece, rate]
            ['MS Rod 10mm 500W', 'এমএস রড ১০মিমি', 'MS Rod', 'BSRM', 'kg', 88, 95, 3000, null, ['Ton', 1000, 94000], null],
            ['MS Rod 12mm 500W', 'এমএস রড ১২মিমি', 'MS Rod', 'BSRM', 'kg', 88, 95, 3000, null, ['Ton', 1000, 94000], null],
            ['Binding Wire', 'বাইন্ডিং তার', 'Binding Wire', null, 'kg', 120, 150, 200, null, null, null],
            ['Portland Cement 50kg', 'পোর্টল্যান্ড সিমেন্ট ৫০ কেজি', 'Cement', 'Shah Cement', 'bag', 480, 530, 300, null, null, null],
            ['Composite Cement 50kg', 'কম্পোজিট সিমেন্ট ৫০ কেজি', 'Cement', 'Seven Rings', 'bag', 465, 515, 250, null, null, null],
            ['Floor Tiles 24x24 Glossy', 'ফ্লোর টাইলস ২৪x২৪', 'Floor Tiles', 'RAK', 'pcs', 300, 380, 400, '8901000000017', ['Carton', 4, 1520], ['sqft', 4, 95]],
            ['Wall Tiles 12x20 Matt', 'ওয়াল টাইলস ১২x২০', 'Wall Tiles', 'DBL', 'pcs', 105, 125, 600, '8901000000024', ['Carton', 10, 1250], ['sqft', 1.666667, 75]],
            ['Mirror Wall Tiles 1.77', 'মিরর ওয়াল টাইলস', 'Wall Tiles', 'Akij', 'pcs', 150, 212.40, 500, '8901000000031', ['Carton', 10, 2124], ['sqft', 1.77, 120]],
            ['Stair Tiles 12x48', 'সিঁড়ির টাইলস ১২x৪৮', 'Stair Tiles', 'RAK', 'pcs', 420, 520, 120, null, ['Carton', 3, 1560], ['sqft', 4, 130]],
            ['Commode One-Piece', 'কমোড ওয়ান-পিস', 'Commode & Basin', 'RAK', 'pcs', 7500, 9500, 6, null, null, null],
            ['Wash Basin with Pedestal', 'বেসিন (পেডেস্টাল সহ)', 'Commode & Basin', 'Stella', 'pcs', 2600, 3400, 8, null, null, null],
            ['Bib Cock Tap', 'বিব কক ট্যাপ', 'Taps & Showers', 'Hatim', 'pcs', 450, 650, 40, null, null, null],
            ['PVC Pipe 1 inch (20 ft)', 'পিভিসি পাইপ ১ ইঞ্চি', 'Pipes & Fittings', 'RFL', 'pcs', 380, 450, 60, null, null, null],
            ['Electric Cable 1.5 rm', 'ইলেকট্রিক ক্যাবল ১.৫ আরএম', 'Cables & Wires', 'BRB', 'm', 20, 24, 1000, null, ['Coil', 100, 2300], null],
            ['Switch 1 Gang', 'সুইচ ১ গ্যাং', 'Switches & Sockets', 'Walton', 'pcs', 45, 70, 200, null, ['Box', 10, 650], null],
            ['LED Bulb 12W', 'এলইডি বাল্ব ১২ ওয়াট', 'Lights & Fans', 'Transtec', 'pcs', 150, 220, 80, null, null, null],
            ['Circuit Breaker 32A', 'সার্কিট ব্রেকার ৩২এ', 'Circuit Breakers', 'Schneider', 'pcs', 280, 400, 30, null, null, null],
            ['Nut Bolt 10mm x 2 inch', 'নাট বোল্ট ১০মিমি', 'Nuts & Bolts', null, 'pcs', 6, 10, 1000, null, ['Box', 100, 900], null],
            ['Wire Nails 3 inch', 'তারকাটা ৩ ইঞ্চি', 'Nails & Screws', null, 'kg', 140, 170, 100, null, null, null],
        ];

        foreach ($items as [$name, $nameBn, $categoryName, $brandName, $unitShort, $cost, $price, $stock, $barcode, $pack, $measure]) {
            $baseUnit = $unit($unitShort);
            $units = [[
                'name' => $baseUnit->name,
                'name_bn' => $baseUnit->name_bn,
                'quantity' => 1,
                'selling_price' => $price,
                'is_base' => true,
                'barcodes' => $barcode ? [['code' => $barcode]] : [],
            ]];

            if ($pack) {
                $units[] = [
                    'name' => $pack[0],
                    'name_bn' => null,
                    'quantity' => $pack[1],
                    'selling_price' => $pack[2],
                    'is_base' => false,
                    'barcodes' => [],
                ];
            }

            $products->save(null, [
                'name' => $name,
                'name_bn' => $nameBn,
                'category_id' => $category($categoryName),
                'brand_id' => $brandName ? $brand($brandName) : null,
                'unit_id' => $baseUnit->id,
                'measure_unit' => $measure[0] ?? null,
                'measure_per_unit' => $measure[1] ?? null,
                'measure_price' => $measure[2] ?? null,
                'cost_price' => $cost,
                'reorder_level' => max(2, (int) round($stock / 10)),
            ], $units, $branch, $stock);
        }
    }

    /**
     * A believable quantity of base units for a demo sale (or purchase) line:
     * rods by the hundred kg, cement by the bag, tiles by the dozen pieces, small parts by the box.
     */
    public static function demoQuantity(ProductUnit $unit, bool $purchase = false): int
    {
        $product = $unit->product;
        $cost = (float) $product->cost_price;
        $short = $product->unit?->short_name;

        [$min, $max] = match (true) {
            $product->isMeasured() => [12, 90],
            $short === 'kg' && $cost < 100 => [100, 600],
            $short === 'kg' => [5, 25],
            $short === 'bag' => [5, 40],
            $short === 'm' => [20, 100],
            $cost < 30 => [10, 60],
            $cost < 500 => [1, 6],
            default => [1, 2],
        };

        return $purchase ? mt_rand($max, $max * 4) : mt_rand($min, $max);
    }

    /**
     * Price fields of a demo line. Tiles are billed by the sqft of the whole pieces.
     *
     * @return array{unit_price: float, measure_unit: string|null, measure_quantity: float|null, measure_rate: float|null, line_total: float}
     */
    public static function demoLine(ProductUnit $unit, int $quantity, bool $atCost = false): array
    {
        $product = $unit->product;

        if ($product->isMeasured()) {
            $measure = $product->measureForPieces($quantity);
            $rate = $atCost ? round((float) $product->cost_price / (float) $product->measure_per_unit, 2) : (float) $product->measure_price;
            $total = round($measure * $rate, 2);

            return ['unit_price' => round($total / $quantity, 2), 'measure_unit' => $product->measure_unit, 'measure_quantity' => $measure, 'measure_rate' => $rate, 'line_total' => $total];
        }

        $price = $atCost ? (float) $product->cost_price : (float) $unit->selling_price;

        return ['unit_price' => $price, 'measure_unit' => null, 'measure_quantity' => null, 'measure_rate' => null, 'line_total' => round($quantity * $price, 2)];
    }
}
