<?php

namespace App\Enums;

/**
 * How a reference person's commission on an invoice is worked out.
 */
enum CommissionType: string
{
    /** A percentage of the invoice's profit. */
    case ProfitPercent = 'profit_percent';

    /** A fixed amount. */
    case Amount = 'amount';

    /**
     * Translated label for showing in the UI.
     */
    public function label(): string
    {
        return __('enums.commission_type.'.$this->value);
    }
}
