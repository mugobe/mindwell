<?php

namespace App\Support;

/**
 * Ugandan mobile numbers, stored one way everywhere: 2567XXXXXXXX (12 digits, no +).
 * Accepts 0772123456, 772123456, 256772123456, +256 772 123 456, etc.
 */
class UgandaPhone
{
    public const MESSAGE = 'Enter a valid Ugandan mobile number, e.g. 0772 123 456.';

    /**
     * Validation rule built on normalize() itself, so "passes validation" and "can be
     * stored" can never disagree (an earlier regex let 077212345678 through, which
     * normalize() then silently turned into null).
     */
    public static function rule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (filled($value) && self::normalize((string) $value) === null) {
                $fail(self::MESSAGE);
            }
        };
    }

    public static function normalize(?string $input): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $input);

        if (str_starts_with($digits, '256') && strlen($digits) === 12) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = substr($digits, 1);
        }

        return (strlen($digits) === 9 && $digits[0] === '7') ? '256'.$digits : null;
    }

    /** @param 'local'|'international'|'plus' $format */
    public static function format(string $normalized, string $format): string
    {
        $local = substr($normalized, 3);

        return match ($format) {
            'international' => '256'.$local,
            'plus' => '+256'.$local,
            default => '0'.$local,
        };
    }

    /** For showing in the UI: 0772 123 456. */
    public static function display(?string $normalized): string
    {
        if (! $normalized || strlen($normalized) !== 12) {
            return (string) $normalized;
        }

        $local = '0'.substr($normalized, 3);

        return substr($local, 0, 4).' '.substr($local, 4, 3).' '.substr($local, 7);
    }
}
