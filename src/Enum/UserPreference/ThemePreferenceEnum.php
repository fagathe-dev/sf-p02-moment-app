<?php

namespace App\Enum\UserPreference;

enum ThemePreferenceEnum: string
{
    case Light = 'light';
    case Dark = 'dark';
    case Auto = 'auto';

    /**
     * @return array<string, string>
     */
    public static function choices(): array
    {
        return array_reduce(static::cases(), fn($carry, $i) => [...$carry, $i->value => $i->value], []);
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_reduce(static::cases(), fn($carry, $i) => [...$carry, $i->value], []);
    }
}