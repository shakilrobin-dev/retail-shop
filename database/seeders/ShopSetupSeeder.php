<?php

namespace Database\Seeders;

use App\Enums\PaymentMethodType;
use App\Models\Branch;
use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Starting data for a new shop: first branch, admin user, units,
 * payment methods, expense categories and starter product categories.
 */
class ShopSetupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Branch::query()->firstOrCreate(['code' => 'B1'], [
            'name' => 'Main Branch',
            'name_bn' => 'প্রধান শাখা',
        ]);

        $admin = User::query()->firstOrCreate(['username' => 'admin'], [
            'name' => 'Admin',
            'password' => env('ADMIN_PASSWORD', 'password'),
            'locale' => 'en',
        ]);
        $admin->assignRole('admin');

        $units = [
            ['Piece', 'পিস', 'pcs', false],
            ['Pair', 'জোড়া', 'pair', false],
            ['Set', 'সেট', 'set', false],
            ['Meter', 'মিটার', 'm', true],
            ['Kg', 'কেজি', 'kg', true],
            ['Ton', 'টন', 'ton', true],
            ['Bag', 'বস্তা', 'bag', false],
            ['Feet', 'ফুট', 'ft', true],
            ['Box', 'বক্স', 'box', false],
            ['Coil', 'কয়েল', 'coil', false],
        ];
        foreach ($units as [$name, $nameBn, $short, $allowsDecimal]) {
            Unit::query()->firstOrCreate(['short_name' => $short], [
                'name' => $name,
                'name_bn' => $nameBn,
                'allows_decimal' => $allowsDecimal,
            ]);
        }

        $paymentMethods = [
            ['Cash', 'ক্যাশ', PaymentMethodType::Cash],
            ['Card', 'কার্ড', PaymentMethodType::Card],
            ['bKash', 'বিকাশ', PaymentMethodType::MobileBanking],
            ['Nagad', 'নগদ', PaymentMethodType::MobileBanking],
            ['Rocket', 'রকেট', PaymentMethodType::MobileBanking],
        ];
        foreach ($paymentMethods as $order => [$name, $nameBn, $type]) {
            PaymentMethod::query()->firstOrCreate(['name' => $name], [
                'name_bn' => $nameBn,
                'type' => $type,
                'sort_order' => $order,
            ]);
        }

        $expenseCategories = [
            ['Rent', 'ভাড়া'],
            ['Salary', 'বেতন'],
            ['Electricity', 'বিদ্যুৎ'],
            ['Internet', 'ইন্টারনেট'],
            ['Transport', 'পরিবহন'],
            ['Food & Tea', 'খাবার ও চা'],
            ['Commission', 'কমিশন'],
            ['Other', 'অন্যান্য'],
        ];
        foreach ($expenseCategories as [$name, $nameBn]) {
            ExpenseCategory::query()->firstOrCreate(['name' => $name], ['name_bn' => $nameBn]);
        }

        $categories = [
            ['Rod & Steel', 'রড ও স্টিল', [
                ['MS Rod', 'এমএস রড', []],
                ['Binding Wire', 'বাইন্ডিং তার', []],
                ['Angle & Flat Bar', 'এঙ্গেল ও ফ্ল্যাট বার', []],
            ]],
            ['Cement', 'সিমেন্ট', []],
            ['Tiles', 'টাইলস', [
                ['Floor Tiles', 'ফ্লোর টাইলস', []],
                ['Wall Tiles', 'ওয়াল টাইলস', []],
                ['Stair Tiles', 'সিঁড়ির টাইলস', []],
            ]],
            ['Sanitary', 'স্যানিটারি', [
                ['Commode & Basin', 'কমোড ও বেসিন', []],
                ['Taps & Showers', 'ট্যাপ ও শাওয়ার', []],
                ['Pipes & Fittings', 'পাইপ ও ফিটিংস', []],
            ]],
            ['Electrical', 'ইলেকট্রিক্যাল', [
                ['Cables & Wires', 'ক্যাবল ও তার', []],
                ['Switches & Sockets', 'সুইচ ও সকেট', []],
                ['Lights & Fans', 'লাইট ও ফ্যান', []],
                ['Circuit Breakers', 'সার্কিট ব্রেকার', []],
            ]],
            ['Hardware', 'হার্ডওয়্যার', [
                ['Nuts & Bolts', 'নাট ও বোল্ট', []],
                ['Nails & Screws', 'পেরেক ও স্ক্রু', []],
                ['Tools', 'যন্ত্রপাতি', []],
            ]],
        ];

        $this->seedCategories($categories);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: array<int, mixed>}>  $categories
     */
    private function seedCategories(array $categories, ?int $parentId = null): void
    {
        foreach ($categories as [$name, $nameBn, $children]) {
            $category = Category::query()->firstOrCreate(['name' => $name, 'parent_id' => $parentId], ['name_bn' => $nameBn]);
            $this->seedCategories($children, $category->id);
        }
    }
}
