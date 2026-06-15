<?php

namespace App\Security\Enum;

enum RoleEnum: string
{
    case ROLE_USER = 'ROLE_USER';
    case ROLE_EDITOR = 'ROLE_EDITOR';
    case ROLE_MANAGER = 'ROLE_MANAGER';
    case ROLE_ADMIN = 'ROLE_ADMIN';
    case ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    /**
     * @param null|string|RoleEnum|null $role
     * 
     * @return string
     */
    public static function getRole(null|string|RoleEnum $role = null): string
    {
        if (is_string($role)) {
            $role = RoleEnum::tryFrom($role);
        }
        if ($role instanceof RoleEnum === false || $role === null) {
            return 'Tous les rôles';
        }

        return static::mapRole($role);
    }

    /**
     * @param RoleEnum $role
     * 
     * @return string
     */
    public static function mapRole(RoleEnum $role): string
    {
        return match ($role) {
            static::ROLE_USER => 'Utilisateur',
            static::ROLE_EDITOR => 'Éditeur',
            static::ROLE_MANAGER => 'Manager',
            static::ROLE_ADMIN => 'Administrateur',
            static::ROLE_SUPER_ADMIN => 'Super Administrateur',
        };
    }

    /**
     * @return array
     */
    public static function choices(): array
    {
        return [
            'Utilisateur' => static::ROLE_USER->value,
            'Éditeur' => static::ROLE_EDITOR->value,
            'Manager' => static::ROLE_MANAGER->value,
            'Administrateur' => static::ROLE_ADMIN->value,
            'Super Administrateur' => static::ROLE_SUPER_ADMIN->value,
        ];
    }

    /**
     * @return array
     */
    public static function values(): array
    {
        return array_map(fn($e) => $e->value, self::cases());
    }
}