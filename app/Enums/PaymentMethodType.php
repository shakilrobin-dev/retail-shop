<?php

namespace App\Enums;

enum PaymentMethodType: string
{
    case Cash = 'cash';
    case Card = 'card';
    case MobileBanking = 'mobile_banking';
    case Other = 'other';

    /**
     * Translated label for showing in the UI.
     */
    public function label(): string
    {
        return __('enums.payment_method_type.'.$this->value);
    }
}
