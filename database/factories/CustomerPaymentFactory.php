<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerPayment>
 */
class CustomerPaymentFactory extends Factory
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
            'customer_id' => Customer::factory(),
            'receipt_no' => 'MR-'.fake()->unique()->numerify('######'),
            'payment_method_id' => PaymentMethod::factory(),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'paid_at' => now(),
        ];
    }
}
