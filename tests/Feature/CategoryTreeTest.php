<?php

namespace Tests\Feature;

use App\Livewire\GlobalSearch;
use App\Livewire\Products\CatalogSetup;
use App\Livewire\Products\ProductIndex;
use App\Models\Category;
use App\Services\CategoryTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryTreeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Category, 1: Category, 2: Category}
     */
    private function threeLevels(): array
    {
        $accessories = Category::factory()->create(['name' => 'Accessories']);
        $chargers = Category::factory()->create(['name' => 'Chargers', 'parent_id' => $accessories->id]);
        $fast = Category::factory()->create(['name' => 'Fast chargers', 'parent_id' => $chargers->id]);

        return [$accessories, $chargers, $fast];
    }

    public function test_tree_order_depth_path_and_descendants(): void
    {
        [$accessories, $chargers, $fast] = $this->threeLevels();
        $other = Category::factory()->create(['name' => 'Batteries']);
        $tree = app(CategoryTree::class);

        $flat = $tree->flat();
        $this->assertSame(['Accessories', 'Chargers', 'Fast chargers', 'Batteries'], $flat->map(fn ($row) => $row['category']->name)->all());
        $this->assertSame([0, 1, 2, 0], $flat->pluck('depth')->all());
        $this->assertSame('Accessories › Chargers › Fast chargers', $tree->path($fast->id));
        $this->assertEqualsCanonicalizing([$accessories->id, $chargers->id, $fast->id], $tree->withDescendants($accessories->id));
        $this->assertNotContains($other->id, $tree->withDescendants($accessories->id));
    }

    public function test_add_sub_category_from_tree(): void
    {
        $this->actingAsRole('admin');
        [, $chargers] = $this->threeLevels();

        Livewire::test(CatalogSetup::class)
            ->call('newCategory', $chargers->id)
            ->assertSet('categoryParentId', $chargers->id)
            ->set('categoryName', 'Car chargers')
            ->call('saveCategory')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', ['name' => 'Car chargers', 'parent_id' => $chargers->id]);
    }

    public function test_category_cannot_be_moved_inside_its_own_branch(): void
    {
        $this->actingAsRole('admin');
        [$accessories, , $fast] = $this->threeLevels();

        Livewire::test(CatalogSetup::class)
            ->call('editCategory', $accessories->id)
            ->set('categoryParentId', $fast->id)
            ->call('saveCategory')
            ->assertHasErrors('categoryParentId');

        $this->assertNull($accessories->fresh()->parent_id);
    }

    public function test_same_name_is_allowed_under_different_parents_but_not_twice_in_one(): void
    {
        $this->actingAsRole('admin');
        [$accessories, $chargers] = $this->threeLevels();
        $spare = Category::factory()->create(['name' => 'Spare parts']);

        Livewire::test(CatalogSetup::class)
            ->call('newCategory', $spare->id)->set('categoryName', 'Chargers')->call('saveCategory')->assertHasNoErrors()
            ->call('newCategory', $accessories->id)->set('categoryName', 'Chargers')->call('saveCategory')->assertHasErrors('categoryName');
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $this->actingAsRole('admin');
        [, , $fast] = $this->threeLevels();
        $this->makeProduct(['category_id' => $fast->id]);

        Livewire::test(CatalogSetup::class)->call('deleteCategory', $fast->id);

        $this->assertNotNull($fast->fresh());
    }

    public function test_filtering_by_main_category_includes_all_levels_below(): void
    {
        $this->actingAsRole('admin');
        [$accessories, , $fast] = $this->threeLevels();
        $this->makeProduct(['name' => 'Anker 30W', 'category_id' => $fast->id]);
        $this->makeProduct(['name' => 'Samsung battery', 'category_id' => Category::factory()->create()->id]);

        Livewire::test(ProductIndex::class)
            ->set('categoryId', $accessories->id)
            ->assertSee('Anker 30W')
            ->assertDontSee('Samsung battery');
    }

    public function test_global_search_finds_products_and_pages(): void
    {
        $this->actingAsRole('admin');
        $this->makeProduct(['name' => 'Baseus Cable'], pieceBarcode: '999111');

        $results = Livewire::test(GlobalSearch::class)->set('query', '999111')->instance()->results();
        $this->assertSame('Baseus Cable', collect($results)->firstWhere('group', __('Products'))['title']);

        $pages = Livewire::test(GlobalSearch::class)->set('query', 'Repor')->instance()->results();
        $this->assertSame(route('reports.index'), $pages[0]['url']);
    }

    public function test_main_pages_render_with_new_layout(): void
    {
        $this->actingAsRole('admin');
        $this->threeLevels();

        $this->get(route('dashboard'))->assertOk()->assertSee(__('Sales – last 30 days'));
        $this->get(route('catalog.setup'))->assertOk()->assertSee('Fast chargers');
        $this->get(route('products.create'))->assertOk()->assertSee('Fast chargers');
    }
}
