<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Unit;
use App\Services\ProductService;
use Illuminate\Database\Seeder;

/**
 * Sample products for trying the system. Not run by default:
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
        $piece = Unit::query()->where('short_name', 'pcs')->firstOrFail();
        $category = fn (string $name) => Category::query()->where('name', $name)->firstOrFail()->id;
        $brand = fn (string $name) => Brand::query()->firstOrCreate(['name' => $name])->id;

        Supplier::query()->firstOrCreate(['name' => 'Dhaka Mobile Wholesale'], ['phone' => '01711000000', 'company' => 'Stadium Market']);
        Customer::query()->firstOrCreate(['phone' => '01811000000'], ['name' => 'Rahim Uddin']);

        $items = [
            // name, name_bn, category, brand, models, cost, price, stock, barcode, warranty, pack [name, qty, price, barcode]
            ['20W Fast Charger Type-C', '২০ ওয়াট ফাস্ট চার্জার', 'Chargers', 'Anker', null, 450, 750, 25, '6901234500011', 180, null],
            ['USB Type-C Cable 1m', 'টাইপ-সি ক্যাবল ১ মিটার', 'Cables', 'Baseus', null, 90, 180, 60, '6901234500028', 30, null],
            ['Lightning Cable 1m', 'লাইটনিং ক্যাবল ১ মিটার', 'Cables', 'Baseus', 'iPhone', 150, 300, 30, '6901234500035', 30, null],
            ['Samsung A15 Back Cover', 'স্যামসাং A15 ব্যাক কভার', 'Covers & Cases', null, 'Samsung A15', 60, 150, 20, null, null, null],
            ['Tempered Glass 9D', 'টেম্পার্ড গ্লাস ৯ডি', 'Screen Protectors', null, 'All models', 25, 100, 100, null, null, null],
            ['Wired Earphone', 'তারযুক্ত ইয়ারফোন', 'Earphones & Headphones', 'Realme', null, 120, 250, 15, '6901234500042', 7, null],
            ['10000mAh Power Bank', '১০০০০ এমএএইচ পাওয়ার ব্যাংক', 'Power Banks', 'Xiaomi', null, 1150, 1650, 8, '6901234500059', 180, null],
            ['A15 Display (OLED)', 'A15 ডিসপ্লে', 'Displays', 'Samsung', 'Samsung A15', 2200, 3200, 3, null, 30, null],
            ['Redmi 12 Battery', 'রেডমি ১২ ব্যাটারি', 'Batteries', 'Xiaomi', 'Redmi 12', 650, 1100, 5, null, 90, null],
            ['SIM Ejector Pin', 'সিম পিন', 'Small Parts', null, null, 1, 5, 200, null, null, ['Box', 50, 200, '6901234500066']],
            ['Charging Port Type-C', 'চার্জিং পোর্ট টাইপ-সি', 'Charging Ports', null, 'Universal', 40, 150, 4, null, null, ['Pack', 10, 1200, null]],
        ];

        foreach ($items as [$name, $nameBn, $categoryName, $brandName, $models, $cost, $price, $stock, $barcode, $warranty, $pack]) {
            $units = [[
                'name' => $piece->name,
                'name_bn' => $piece->name_bn,
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
                    'barcodes' => $pack[3] ? [['code' => $pack[3]]] : [],
                ];
            }

            $products->save(null, [
                'name' => $name,
                'name_bn' => $nameBn,
                'category_id' => $category($categoryName),
                'brand_id' => $brandName ? $brand($brandName) : null,
                'unit_id' => $piece->id,
                'compatible_models' => $models,
                'cost_price' => $cost,
                'reorder_level' => max(2, (int) round($stock / 5)),
                'warranty_days' => $warranty,
            ], $units, $branch, $stock);
        }
    }
}
