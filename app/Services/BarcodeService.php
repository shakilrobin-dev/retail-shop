<?php

namespace App\Services;

use App\Models\Barcode;
use Picqer\Barcode\Renderers\SvgRenderer;
use Picqer\Barcode\Types\TypeCode128;

/**
 * Creates shop barcodes and draws barcode images for labels.
 *
 * Generated codes are EAN-13 numbers starting with the shop prefix
 * (default "20"; prefixes 20–29 are reserved for in-store use, so they
 * never clash with real product barcodes).
 */
class BarcodeService
{
    public function __construct(private Settings $settings) {}

    /**
     * A new barcode that is not used yet.
     *
     * @param  list<string>  $alsoAvoid  codes already taken in the form being edited
     */
    public function generate(array $alsoAvoid = []): string
    {
        $prefix = preg_replace('/\D/', '', (string) $this->settings->get('barcode_prefix')) ?: '20';
        $prefix = substr($prefix, 0, 6);
        $next = Barcode::query()->where('is_generated', true)->count() + 1;

        do {
            $body = $prefix.str_pad((string) $next, 12 - strlen($prefix), '0', STR_PAD_LEFT);
            $code = $body.$this->ean13CheckDigit($body);
            $next++;
        } while (in_array($code, $alsoAvoid, true) || Barcode::query()->where('code', $code)->exists());

        return $code;
    }

    /**
     * SVG image of the barcode bars (Code 128 reads any code, letters too).
     */
    public function svg(string $code, int $width = 200, int $height = 50): string
    {
        $barcode = (new TypeCode128)->getBarcode($code);

        return (new SvgRenderer)->render($barcode, $width, $height);
    }

    private function ean13CheckDigit(string $twelveDigits): int
    {
        $sum = 0;

        foreach (str_split($twelveDigits) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return (10 - ($sum % 10)) % 10;
    }
}
