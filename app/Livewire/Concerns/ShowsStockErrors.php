<?php

namespace App\Livewire\Concerns;

use App\Exceptions\InsufficientStockException;
use Closure;
use Throwable;

/**
 * Shows "not enough stock" as an error toast (and a "stock" form error) instead of an error page.
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
