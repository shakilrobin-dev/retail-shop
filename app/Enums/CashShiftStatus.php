<?php

namespace App\Enums;

enum CashShiftStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    /**
     * Translated label for showing in the UI.
     */
    public function label(): string
    {
        return __('enums.cash_shift_status.'.$this->value);
    }
}
