<?php

namespace Tests\Feature;

use App\Livewire\Products\CatalogQuickAdd;
use App\Livewire\Products\ProductForm;
use App\Livewire\Products\QuickProductForm;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogQuickAddTest extends TestCase
{
    use RefreshDatabase;

    private Category $tiles;

    private Category $floor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsRole('admin', Branch::factory()->create(['code' => 'B1']));
        Unit::factory()->create();
        $this->tiles = Category::factory()->create(['name' => 'Tiles', 'parent_id' => null]);
        $this->floor = Category::factory()->create(['name' => 'Floor Tiles', 'parent_id' => $this->tiles->id]);
        Category::factory()->create(['name' => 'Cement', 'parent_id' => null]);
    }

    public function test_category_and_sub_category_pickers_set_the_product_category(): void
    {
        Livewire::test(ProductForm::class)
            ->assertSee('Sub-category')
            ->set('mainCategoryId', $this->tiles->id)
            ->assertSet('category_id', $this->tiles->id)
            ->assertSee('Floor Tiles')
            ->set('subCategoryId', $this->floor->id)
            ->assertSet('category_id', $this->floor->id)
            ->set('mainCategoryId', Category::query()->where('name', 'Cement')->value('id'))
            ->assertSet('subCategoryId', null)
            ->assertSet('category_id', Category::query()->where('name', 'Cement')->value('id'));
    }

    public function test_editing_a_product_fills_both_pickers(): void
    {
        $product = $this->makeProduct(['category_id' => $this->floor->id]);

        Livewire::test(ProductForm::class, ['product' => $product])
            ->assertSet('mainCategoryId', $this->tiles->id)
            ->assertSet('subCategoryId', $this->floor->id);
    }

    public function test_new_sub_category_is_added_in_the_pop_up_and_chosen_in_the_form(): void
    {
        $form = Livewire::test(ProductForm::class)
            ->set('mainCategoryId', $this->tiles->id)
            ->call('addCatalogItem', 'subcategory')
            ->assertDispatched('open-catalog-quick-add', type: 'subcategory', parentId: $this->tiles->id, target: 'products.product-form');

        Livewire::test(CatalogQuickAdd::class)
            ->call('openForm', 'subcategory', $this->tiles->id, 'products.product-form')
            ->assertSet('open', true)
            ->assertSee('New sub-category')
            ->set('name', 'Floor Tiles')
            ->call('save')
            ->assertHasErrors('name')
            ->set('name', 'Wall Tiles')
            ->set('name_bn', 'ওয়াল টাইলস')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('open', false)
            ->assertDispatched('catalog-item-added');

        $wall = Category::query()->where('name', 'Wall Tiles')->sole();
        $this->assertSame($this->tiles->id, $wall->parent_id);
        $this->assertSame('ওয়াল টাইলস', $wall->name_bn);

        $form->dispatch('catalog-item-added', type: 'subcategory', id: $wall->id, target: 'products.product-form')
            ->assertSet('mainCategoryId', $this->tiles->id)
            ->assertSet('subCategoryId', $wall->id)
            ->assertSet('category_id', $wall->id);
    }

    public function test_new_brand_and_unit_are_chosen_and_other_forms_ignore_them(): void
    {
        Livewire::test(CatalogQuickAdd::class)
            ->call('openForm', 'brand', null, 'products.product-form')
            ->set('name', 'RAK')->call('save')->assertHasNoErrors()
            ->call('openForm', 'brand', null, 'products.product-form')
            ->set('name', 'RAK')->call('save')->assertHasErrors('name')
            ->call('openForm', 'unit', null, 'products.product-form')
            ->set('name', 'Bundle')->call('save')->assertHasErrors('short_name')
            ->set('short_name', 'bdl')->set('allows_decimal', false)->call('save')->assertHasNoErrors();

        $brand = Brand::query()->where('name', 'RAK')->sole();
        $unit = Unit::query()->where('short_name', 'bdl')->sole();

        Livewire::test(ProductForm::class)
            ->dispatch('catalog-item-added', type: 'brand', id: $brand->id, target: 'products.product-form')
            ->dispatch('catalog-item-added', type: 'unit', id: $unit->id, target: 'products.product-form')
            ->assertSet('brand_id', $brand->id)
            ->assertSet('unit_id', $unit->id)
            ->dispatch('catalog-item-added', type: 'category', id: $this->tiles->id, target: 'products.quick-product-form')
            ->assertSet('category_id', null);
    }

    public function test_quick_add_product_pop_up_has_sub_category_and_brand(): void
    {
        $brand = Brand::query()->create(['name' => 'RAK']);

        Livewire::test(QuickProductForm::class)
            ->call('openForm', 'Matt Floor Tile')
            ->set('mainCategoryId', $this->tiles->id)
            ->set('subCategoryId', $this->floor->id)
            ->set('brand_id', $brand->id)
            ->set('selling_price', '120')
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::query()->where('name', 'Matt Floor Tile')->sole();
        $this->assertSame($this->floor->id, $product->category_id);
        $this->assertSame($brand->id, $product->brand_id);
    }
}
