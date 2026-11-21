<?php

namespace App\Enums;

/**
 * How much of an invoice is paid.
 */
enum PaymentStatus: string
{
    case Paid = 'paid';
    case Partial = 'partial';
    case Due = 'due';

    /**
     * Translated label for showing in the UI.
     */
    public function label(): string
    {
        return __('enums.payment_status.'.$this->value);
    }

    /**
     * Badge colour.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Paid => 'green',
            self::Partial => 'amber',
            self::Due => 'red',
        };
    }

    public static function for(float $total, float $due): self
    {
        if ($due <= 0.009) {
            return self::Paid;
        }

        return $due + 0.009 >= $total ? self::Due : self::Partial;
    }
}
