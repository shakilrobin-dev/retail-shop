<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Purchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'reference_no' => 'PUR-'.fake()->unique()->numerify('#####'),
            'purchase_date' => now()->toDateString(),
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
        ];
    }
}
