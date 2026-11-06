<?php

namespace App\Exceptions;

use App\Models\Product;
use Exception;

class InsufficientStockException extends Exception
{
    public function __construct(public Product $product, public float $available, public float $requested)
    {
        parent::__construct(__('Not enough stock for :product. Available: :available, needed: :requested.', [
            'product' => $product->name,
            'available' => $available + 0,
            'requested' => $requested + 0,
        ]));
    }
}
