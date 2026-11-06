<?php

namespace App\Enums;

enum AdjustmentReason: string
{
    case Damaged = 'damaged';
    case Lost = 'lost';
    case InternalUse = 'internal_use';
    case Found = 'found';
    case CountCorrection = 'count_correction';

    /**
     * Translated label for showing in the UI.
     */
    public function label(): string
    {
        return __('enums.adjustment_reason.'.$this->value);
    }
}
