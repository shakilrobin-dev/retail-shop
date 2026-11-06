<?php

namespace App\Livewire\Concerns;

use App\Exceptions\InsufficientStockException;
use Closure;
use Throwable;

/**
 * Shows "not enough stock" as a normal form error instead of an error page.
 * The error is added under the "stock" key.
 */
trait ShowsStockErrors
{
    public function exception(Throwable $e, Closure $stopPropagation): void
    {
        if ($e instanceof InsufficientStockException) {
            $this->addError('stock', $e->getMessage());
            $stopPropagation();
        }
    }
}
