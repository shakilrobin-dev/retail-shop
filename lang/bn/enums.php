<?php

return [
    'sale_status' => [
        'held' => 'হোল্ডে',
        'completed' => 'সম্পন্ন',
        'voided' => 'বাতিল',
    ],
    'repair_status' => [
        'received' => 'গ্রহণ করা হয়েছে',
        'diagnosing' => 'পরীক্ষা চলছে',
        'waiting_for_parts' => 'পার্টসের অপেক্ষায়',
        'repairing' => 'মেরামত চলছে',
        'ready' => 'প্রস্তুত',
        'delivered' => 'ডেলিভারি হয়েছে',
        'cancelled' => 'বাতিল',
    ],
    'transfer_status' => [
        'in_transit' => 'পথে আছে',
        'received' => 'গ্রহণ করা হয়েছে',
        'cancelled' => 'বাতিল',
    ],
    'cash_shift_status' => [
        'open' => 'চালু',
        'closed' => 'বন্ধ',
    ],
    'payment_direction' => [
        'in' => 'গ্রহণ',
        'out' => 'প্রদান',
    ],
    'payment_method_type' => [
        'cash' => 'ক্যাশ',
        'card' => 'কার্ড',
        'mobile_banking' => 'মোবাইল ব্যাংকিং',
        'other' => 'অন্যান্য',
    ],
    'adjustment_reason' => [
        'damaged' => 'নষ্ট',
        'lost' => 'হারানো',
        'internal_use' => 'নিজস্ব ব্যবহার',
        'found' => 'পাওয়া গেছে',
        'count_correction' => 'গণনা সংশোধন',
    ],
    'discount_type' => [
        'amount' => 'টাকা',
        'percent' => 'শতাংশ',
    ],
    'stock_movement_type' => [
        'opening' => 'প্রারম্ভিক স্টক',
        'purchase' => 'ক্রয়',
        'purchase_return' => 'ক্রয় ফেরত',
        'sale' => 'বিক্রয়',
        'sale_return' => 'বিক্রয় ফেরত',
        'sale_void' => 'বিক্রয় বাতিল',
        'adjustment' => 'সমন্বয়',
        'transfer_out' => 'ট্রান্সফার (পাঠানো)',
        'transfer_in' => 'ট্রান্সফার (গ্রহণ)',
        'repair_usage' => 'মেরামতে ব্যবহৃত',
        'repair_return' => 'মেরামত থেকে ফেরত',
    ],
];
