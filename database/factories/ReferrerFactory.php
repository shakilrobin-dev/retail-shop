<?php

namespace Database\Factories;

use App\Models\Referrer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Referrer>
 */
class ReferrerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '01'.fake()->numerify('#########'),
            'commission_percent' => null,
            'is_active' => true,
        ];
    }

    /**
     * Gets this % of the invoice profit by default.
     */
    public function withPercent(float $percent): static
    {
        return $this->state(fn (array $attributes): array => ['commission_percent' => $percent]);
    }
}
