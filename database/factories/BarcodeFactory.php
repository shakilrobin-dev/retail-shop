<?php

namespace Database\Factories;

use App\Models\Barcode;
use App\Models\ProductUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Barcode>
 */
class BarcodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_unit_id' => ProductUnit::factory(),
            'product_id' => fn (array $attributes) => ProductUnit::find($attributes['product_unit_id'])->product_id,
            'code' => fake()->unique()->ean13(),
            'is_generated' => false,
        ];
    }
}
