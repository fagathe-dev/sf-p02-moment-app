<?php

namespace App\Enum;
enum EntryColorEnum: string
{
    case Danger = 'danger';
    case Dark = 'dark';
    case Info = 'info';
    case Light = 'light';
    case Primary = 'primary';
    case Secondary = 'secondary';
    case Success = 'success';
    case Warning = 'warning';

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