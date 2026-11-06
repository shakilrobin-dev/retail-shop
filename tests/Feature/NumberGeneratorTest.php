<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Services\NumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class NumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_numbers_run_in_order_per_branch_and_type(): void
    {
        $mainBranch = Branch::factory()->create(['code' => 'B1']);
        $secondBranch = Branch::factory()->create(['code' => 'B2']);
        $numbers = app(NumberGenerator::class);

        $this->assertSame('B1-INV-000001', $numbers->next($mainBranch, 'invoice'));
        $this->assertSame('B1-INV-000002', $numbers->next($mainBranch, 'invoice'));
        $this->assertSame('B1-JOB-000001', $numbers->next($mainBranch, 'repair'));
        $this->assertSame('B2-INV-000001', $numbers->next($secondBranch, 'invoice'));
    }

    public function test_unknown_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(NumberGenerator::class)->next(Branch::factory()->create(), 'unknown');
    }
}
