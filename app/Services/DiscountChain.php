<?php

namespace App\Services;

/**
 * Item discount written as consecutive percentages, e.g. "20,10,5":
 * 100 − 20% = 80, − 10% = 72, − 5% = 68.40.
 */
class DiscountChain
{
    private const DIGITS = ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9'];

    /**
     * The percentages, [] when empty, or null when the text is not valid.
     *
     * @return list<float>|null
     */
    public static function parse(?string $text): ?array
    {
        $text = trim(strtr((string) $text, self::DIGITS + ['%' => '', '+' => ',', '،' => ',']), " ,\t");

        if ($text === '') {
            return [];
        }

        $percents = [];

        foreach (preg_split('/\s*,\s*/', $text) ?: [] as $part) {
            if (! is_numeric($part) || (float) $part < 0 || (float) $part > 100) {
                return null;
            }

            if ((float) $part > 0) {
                $percents[] = (float) $part;
            }
        }

        return $percents;
    }

    public static function isValid(?string $text): bool
    {
        return self::parse($text) !== null;
    }

    /**
     * Money taken off $gross by the chain of percentages.
     */
    public static function amount(float $gross, ?string $text): float
    {
        $percents = self::parse($text) ?? [];
        $remaining = 1.0;

        foreach ($percents as $percent) {
            $remaining *= 1 - $percent / 100;
        }

        return round($gross * (1 - $remaining), 2);
    }

    /**
     * Clean form for saving, e.g. "20, 10,5%" → "20,10,5". Null when empty or not valid.
     */
    public static function normalize(?string $text): ?string
    {
        $percents = self::parse($text);

        return $percents ? implode(',', array_map(fn (float $percent): string => (string) ($percent + 0), $percents)) : null;
    }

    /**
     * "20% + 10% + 5%" for receipts.
     */
    public static function label(?string $text): string
    {
        return implode(' + ', array_map(fn (float $percent): string => shop_digits($percent + 0).'%', self::parse($text) ?? []));
    }
}
