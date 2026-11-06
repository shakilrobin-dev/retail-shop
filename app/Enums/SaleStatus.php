<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Held = 'held';
    case Completed = 'completed';
    case Voided = 'voided';

    /**
     * Translated label for showing in the UI.
     */
    public function label(): string
    {
        return __('enums.sale_status.'.$this->value);
    }
}
