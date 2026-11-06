<?php

namespace Database\Factories;

use App\Enums\RepairStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\RepairJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepairJob>
 */
class RepairJobFactory extends Factory
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
            'job_no' => 'JOB-'.fake()->unique()->numerify('#####'),
            'status' => RepairStatus::Received,
            'customer_id' => Customer::factory(),
            'device_type' => 'phone',
            'device_brand' => fake()->randomElement(['Samsung', 'Xiaomi', 'Realme', 'Oppo']),
            'device_model' => fake()->bothify('?-##'),
            'problem' => fake()->sentence(),
        ];
    }
}
