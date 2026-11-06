<?php

return [
    'sale_status' => [
        'held' => 'On hold',
        'completed' => 'Completed',
        'voided' => 'Voided',
    ],
    'repair_status' => [
        'received' => 'Received',
        'diagnosing' => 'Diagnosing',
        'waiting_for_parts' => 'Waiting for parts',
        'repairing' => 'Repairing',
        'ready' => 'Ready',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ],
    'transfer_status' => [
        'in_transit' => 'In transit',
        'received' => 'Received',
        'cancelled' => 'Cancelled',
    ],
    'cash_shift_status' => [
        'open' => 'Open',
        'closed' => 'Closed',
    ],
    'payment_direction' => [
        'in' => 'Received',
        'out' => 'Paid',
    ],
    'payment_method_type' => [
        'cash' => 'Cash',
        'card' => 'Card',
        'mobile_banking' => 'Mobile banking',
        'other' => 'Other',
    ],
    'adjustment_reason' => [
        'damaged' => 'Damaged',
        'lost' => 'Lost',
        'internal_use' => 'Internal use',
        'found' => 'Found',
        'count_correction' => 'Count correction',
    ],
    'discount_type' => [
        'amount' => 'Amount',
        'percent' => 'Percent',
    ],
    'stock_movement_type' => [
        'opening' => 'Opening stock',
        'purchase' => 'Purchase',
        'purchase_return' => 'Purchase return',
        'sale' => 'Sale',
        'sale_return' => 'Sale return',
        'sale_void' => 'Sale void',
        'adjustment' => 'Adjustment',
        'transfer_out' => 'Transfer out',
        'transfer_in' => 'Transfer in',
        'repair_usage' => 'Used in repair',
        'repair_return' => 'Returned from repair',
    ],
];
