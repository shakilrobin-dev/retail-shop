<?php

namespace App\Enums;

enum TransferStatus: string
{
    case InTransit = 'in_transit';
    case Received = 'received';
    case Cancelled = 'cancelled';

    /**
     * Translated label for showing in the UI.
     */
    public function label(): string
    {
        return __('enums.transfer_status.'.$this->value);
    }
}
