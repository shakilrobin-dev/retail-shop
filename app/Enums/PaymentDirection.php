<?php

namespace App\Enums;

enum PaymentDirection: string
{
    case In = 'in';
    case Out = 'out';

    /**
     * Translated label for showing in the UI.
     */
    public function label(): string
    {
        return __('enums.payment_direction.'.$this->value);
    }
}
