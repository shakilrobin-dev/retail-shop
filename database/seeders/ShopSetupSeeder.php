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
            ['Other', 'অন্যান্য'],
        ];
        foreach ($expenseCategories as [$name, $nameBn]) {
            ExpenseCategory::query()->firstOrCreate(['name' => $name], ['name_bn' => $nameBn]);
        }

        $categories = [
            ['Accessories', 'এক্সেসরিজ', [
                ['Chargers', 'চার্জার'],
                ['Cables', 'ক্যাবল'],
                ['Covers & Cases', 'কভার ও কেস'],
                ['Screen Protectors', 'স্ক্রিন প্রটেক্টর'],
                ['Earphones & Headphones', 'ইয়ারফোন ও হেডফোন'],
                ['Power Banks', 'পাওয়ার ব্যাংক'],
            ]],
            ['Spare Parts', 'স্পেয়ার পার্টস', [
                ['Displays', 'ডিসপ্লে'],
                ['Batteries', 'ব্যাটারি'],
                ['Charging Ports', 'চার্জিং পোর্ট'],
                ['Small Parts', 'ছোট পার্টস'],
            ]],
            ['Electronics', 'ইলেকট্রনিক্স', []],
        ];
        foreach ($categories as [$name, $nameBn, $children]) {
            $parent = Category::query()->firstOrCreate(['name' => $name, 'parent_id' => null], ['name_bn' => $nameBn]);

            foreach ($children as [$childName, $childNameBn]) {
                Category::query()->firstOrCreate(['name' => $childName, 'parent_id' => $parent->id], ['name_bn' => $childNameBn]);
            }
        }
    }
}
