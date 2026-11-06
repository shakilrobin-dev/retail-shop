<?php

namespace Tests\Feature;

use App\Livewire\Reports\ReportIndex;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\RepairJob;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\DemoHistorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Opens every main page with a full month of demo data,
 * so missing eager loads and slow queries show up.
 */
class FullDataPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_pages_render_with_a_month_of_data(): void
    {
        $this->seed([DatabaseSeeder::class, DemoDataSeeder::class, DemoHistorySeeder::class]);
        $admin = User::query()->where('username', 'admin')->sole();
        $this->actingAs($admin);

        $this->assertGreaterThan(50, Sale::query()->count());

        $pages = [
            route('dashboard'), route('pos'), route('sales.index'), route('sales.index', ['status' => '']),
            route('customers.index'), route('cash-register'), route('repairs.index'), route('repairs.index', ['view' => 'list', 'status' => 'all']),
            route('products.index'), route('products.create'), route('catalog.setup'), route('labels.index'),
            route('stock.index'), route('stock.movements'), route('stock.adjust'), route('stock.transfers.index'),
            route('purchases.index'), route('purchases.create'), route('suppliers.index'), route('expenses.index'),
            route('settings.general'), route('settings.users'), route('settings.branches'), route('settings.activity'),
            route('sales.show', Sale::query()->latest('id')->first()), route('purchases.show', Purchase::query()->first()),
            route('repairs.show', RepairJob::query()->first()), route('customers.show', Customer::query()->first()),
            route('products.edit', Product::query()->first()),
        ];

        foreach (ReportIndex::TABS as $tab) {
            $pages[] = route('reports.index', ['tab' => $tab, 'from' => today()->subDays(30)->toDateString()]);
        }

        foreach ($pages as $url) {
            $this->get($url)->assertOk();
        }
    }
}
