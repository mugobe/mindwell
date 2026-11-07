<?php

namespace App\Support;

/**
 * Whole amounts in English words for receipts (2026-11-01, hospital-style receipt):
 *   AmountInWords::make(15000)          → "Fifteen thousand"
 *   AmountInWords::forReceipt(15000)    → "Fifteen thousand Uganda shillings only"
 * UGX has no cents in practice, so amounts are rounded to whole shillings.
 */
class AmountInWords
{
    private const ONES = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
        'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];

    private const TENS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

    private const SCALES = [1_000_000_000 => 'billion', 1_000_000 => 'million', 1_000 => 'thousand'];

    public static function make(float|int $amount): string
    {
        $n = (int) round(abs($amount));

        return ucfirst(self::words($n));
    }

    public static function forReceipt(float|int $amount, string $currency = 'UGX'): string
    {
        $name = strtoupper($currency) === 'UGX' ? 'Uganda shillings' : strtoupper($currency);

        return self::make($amount).' '.$name.' only';
    }

    private static function words(int $n): string
    {
        if ($n < 20) {
            return self::ONES[$n];
        }
        if ($n < 100) {
            return self::TENS[intdiv($n, 10)].($n % 10 ? '-'.self::ONES[$n % 10] : '');
        }
        if ($n < 1000) {
            $rest = $n % 100;

            return self::ONES[intdiv($n, 100)].' hundred'.($rest ? ' and '.self::words($rest) : '');
        }

        foreach (self::SCALES as $size => $label) {
            if ($n >= $size) {
                $rest = $n % $size;
                $head = self::words(intdiv($n, $size)).' '.$label;
                if (! $rest) {
                    return $head;
                }

                return $head.($rest < 100 ? ' and ' : ' ').self::words($rest);
            }
        }

        return (string) $n;
    }
}
