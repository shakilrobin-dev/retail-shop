<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => 'P'.fake()->unique()->numerify('######'),
            'name' => fake()->words(3, true),
            'category_id' => Category::factory(),
            'unit_id' => Unit::factory(),
            'cost_price' => fake()->randomFloat(2, 10, 500),
            'vat_rate' => 0,
            'reorder_level' => 5,
            'is_active' => true,
        ];
    }
}
