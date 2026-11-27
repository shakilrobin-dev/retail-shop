<?php

namespace Database\Factories;

use App\Enums\CommissionType;
use App\Models\Branch;
use App\Models\CommissionSettlement;
use App\Models\Referrer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionSettlement>
 */
class CommissionSettlementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'referrer_id' => Referrer::factory(),
            'branch_id' => Branch::factory(),
            'period_from' => today()->startOfMonth()->toDateString(),
            'period_to' => today()->toDateString(),
            'invoices' => 1,
            'sales' => 1000,
            'profit' => 200,
            'commission_type' => CommissionType::Amount,
            'commission_value' => 50,
            'amount' => 50,
        ];
    }
}
