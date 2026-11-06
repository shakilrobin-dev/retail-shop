<?php

namespace Tests;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Log in as a user with the given role, working in a branch.
     */
    protected function actingAsRole(string $role = 'admin', ?Branch $branch = null): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $branch ??= Branch::query()->first() ?? Branch::factory()->create(['code' => 'B1']);
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    /**
     * A product with a base "Piece" unit, an optional box unit and barcodes.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function makeProduct(array $attributes = [], float $piecePrice = 10, ?string $pieceBarcode = null, ?int $boxQuantity = null, ?float $boxPrice = null, ?string $boxBarcode = null): Product
    {
        $unit = Unit::query()->first() ?? Unit::factory()->create();
        $category = Category::query()->first() ?? Category::factory()->create();

        $product = Product::factory()->create(array_merge([
            'unit_id' => $unit->id,
            'category_id' => $category->id,
            'cost_price' => 5,
        ], $attributes));

        $piece = ProductUnit::factory()->create(['product_id' => $product->id, 'name' => 'Piece', 'quantity' => 1, 'selling_price' => $piecePrice, 'is_base' => true]);

        if ($pieceBarcode) {
            $piece->barcodes()->create(['product_id' => $product->id, 'code' => $pieceBarcode]);
        }

        if ($boxQuantity) {
            $box = ProductUnit::factory()->create(['product_id' => $product->id, 'name' => 'Box', 'quantity' => $boxQuantity, 'selling_price' => $boxPrice ?? $piecePrice * $boxQuantity, 'is_base' => false]);

            if ($boxBarcode) {
                $box->barcodes()->create(['product_id' => $product->id, 'code' => $boxBarcode]);
            }
        }

        return $product->load('units.barcodes');
    }
}
