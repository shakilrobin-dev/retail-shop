<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\NumberSequence;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates running document numbers per branch, e.g. B1-INV-000001.
 * The sequence row is locked, so two cashiers never get the same number.
 */
class NumberGenerator
{
    public function next(Branch $branch, string $type): string
    {
        $prefix = config("shop.number_prefixes.{$type}");

        if ($prefix === null) {
            throw new InvalidArgumentException("Unknown number type [{$type}].");
        }

        $number = DB::transaction(function () use ($branch, $type): int {
            NumberSequence::query()->firstOrCreate(
                ['branch_id' => $branch->id, 'type' => $type],
                ['next_number' => 1],
            );

            $sequence = NumberSequence::query()
                ->where('branch_id', $branch->id)
                ->where('type', $type)
                ->lockForUpdate()
                ->firstOrFail();

            $current = $sequence->next_number;
            $sequence->update(['next_number' => $current + 1]);

            return $current;
        });

        return sprintf('%s-%s-%06d', $branch->code, $prefix, $number);
    }
}
