<?php

namespace App\Enums;

enum RepairStatus: string
{
    case Received = 'received';
    case Diagnosing = 'diagnosing';
    case WaitingForParts = 'waiting_for_parts';
    case Repairing = 'repairing';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /**
     * Translated label for showing in the UI.
     */
    public function label(): string
    {
        return __('enums.repair_status.'.$this->value);
    }
}
