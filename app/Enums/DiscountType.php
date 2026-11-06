<?php

namespace App\Enums;

enum DiscountType: string
{
    case Amount = 'amount';
    case Percent = 'percent';

    /**
     * Translated label for showing in the UI.
     */
    public function label(): string
    {
        return __('enums.discount_type.'.$this->value);
    }
}
