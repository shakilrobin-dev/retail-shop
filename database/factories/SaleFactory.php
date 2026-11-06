<?php

namespace Database\Factories;

use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
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
            'invoice_no' => 'INV-'.fake()->unique()->numerify('#####'),
            'status' => SaleStatus::Completed,
            'sold_at' => now(),
            'subtotal' => 0,
            'total' => 0,
        ];
    }
}
