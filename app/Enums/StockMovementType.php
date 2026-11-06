<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Opening = 'opening';
    case Purchase = 'purchase';
    case PurchaseReturn = 'purchase_return';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case SaleVoid = 'sale_void';
    case Adjustment = 'adjustment';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case RepairUsage = 'repair_usage';
    case RepairReturn = 'repair_return';

    /**
     * Translated label for showing in the UI.
     */
    public function label(): string
    {
        return __('enums.stock_movement_type.'.$this->value);
    }
}
