<?php

/**
 * Default values for admin settings. Values saved in the `settings`
 * table (global or per branch) override these.
 */
return [

    'defaults' => [
        // Shop info
        'shop_name' => 'My Shop',
        'shop_name_bn' => null,
        'shop_phone' => null,
        'shop_logo' => null,
        'vat_bin' => null,
        'currency_symbol' => '৳',

        // Language: 'en' or 'bn'. Digits: 'en' (123) or 'bn' (১২৩)
        'default_locale' => 'en',
        'digits' => 'en',

        // Receipt: print_mode = 'on' | 'off' | 'ask'
        'receipt_print_mode' => 'ask',
        'receipt_paper' => '80',
        'receipt_language' => 'en',
        'receipt_show_logo' => true,
        'receipt_header' => null,
        'receipt_header_bn' => null,
        'receipt_footer' => 'Thank you for shopping with us.',
        'receipt_footer_bn' => 'আমাদের সাথে কেনাকাটার জন্য ধন্যবাদ।',

        // Barcode labels: output = 'a4' | 'thermal'
        'barcode_prefix' => '20',
        'label_output' => 'a4',
        'label_size' => '38x25',
        'label_a4_layout' => '3x8',
        'label_show_shop_name' => true,
        'label_show_price' => true,

        // VAT
        'vat_enabled' => false,
        'vat_default_rate' => 0,
        'vat_inclusive' => false,

        // POS rules
        'pos_allow_price_edit' => false,
        'pos_max_discount_percent' => 10,
        'pos_allow_negative_stock' => false,
        'return_policy_days' => 7,

        // Repair
        'repair_default_warranty_days' => 7,
        'repair_terms' => 'Device must be collected within 30 days. Shop is not responsible for data loss.',
        'repair_terms_bn' => null,

        // Official report documents (PDF / Excel)
        'report_tagline' => null,
        'report_footer_note' => 'This is a computer generated report.',
        'report_show_signatures' => true,

        // Optional features (off by default)
        'cash_drawer_enabled' => false,
        'technicians_enabled' => false,
        'sms_enabled' => false,
        'sms_api_url' => null,
        'sms_api_key' => null,
        'sms_sender_id' => null,
        'sms_events' => [],
    ],

    /**
     * Prefixes for document numbers, e.g. B1-INV-000001.
     */
    'number_prefixes' => [
        'invoice' => 'INV',
        'purchase' => 'PUR',
        'purchase_return' => 'PRT',
        'sale_return' => 'SRT',
        'repair' => 'JOB',
        'adjustment' => 'ADJ',
        'transfer' => 'TRF',
    ],

];
