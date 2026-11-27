<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
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
            'quotation_no' => 'QT-'.fake()->unique()->numerify('######'),
            'customer_name' => fake()->name(),
            'quotation_date' => today()->toDateString(),
            'valid_until' => today()->addDays(7)->toDateString(),
        ];
    }
}
