<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * Staff sidebar colour (2026-11-03, Collins: keep the green as an option). One choice
 * for the whole clinic, set in Admin → Setup → Appearance. Navy is the default.
 */
class StaffAppearance
{
    public const KEY = 'staff.sidebar';

    public const OPTIONS = [
        'navy' => 'Navy (default)',
        'green' => 'Mindwell green',
    ];

    public static function sidebar(): string
    {
        $value = AppSetting::get(self::KEY, 'navy');

        return array_key_exists((string) $value, self::OPTIONS) ? (string) $value : 'navy';
    }
}
