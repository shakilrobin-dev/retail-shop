<?php

use App\Services\Settings;

if (! function_exists('shop_digits')) {
    /**
     * Convert English digits to Bangla digits when the language is Bangla
     * and the "digits" setting is 'bn'.
     */
    function shop_digits(string|int|float $value): string
    {
        $value = (string) $value;

        if (app()->getLocale() !== 'bn' || app(Settings::class)->get('digits') !== 'bn') {
            return $value;
        }

        return strtr($value, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
    }
}

if (! function_exists('money')) {
    /**
     * Format an amount with the shop currency, e.g. "৳ 1,250.00".
     */
    function money(string|int|float|null $amount): string
    {
        $symbol = app(Settings::class)->get('currency_symbol');

        return $symbol.' '.shop_digits(number_format((float) $amount, 2));
    }
}
