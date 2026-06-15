<?php
namespace App\Security;

use App\Entity\User;
use App\Security\Enum\RoleEnum;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

final class RoleChecker
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function isAdmin(): bool
    {
        return $this->check(RoleEnum::ROLE_ADMIN);
    }

    /**
     * Convertit un rôle en niveau de puissance (0-4).
     * Rendue publique pour être utilisée dans les Voters pour les comparaisons strictes.
     */
    public function level(RoleEnum $role): int
    {
        return match ($role) {
            RoleEnum::ROLE_SUPER_ADMIN => 4,
            RoleEnum::ROLE_ADMIN => 3,
            RoleEnum::ROLE_MANAGER => 2,
            RoleEnum::ROLE_EDITOR => 1,
            RoleEnum::ROLE_USER => 0,
            default => -1,
        };
    }

    /**
     * Récupère le niveau de l'utilisateur connecté.
     */
    public function getCurrentUserLevel(User $user): int
    {
        if ($user === null) {
            $user = $this->security->getUser();
        }

        if (!$user instanceof UserInterface) {
            return -1;
        }

        $roles = $user->getRoles();
        $role = array_pop($roles);
        $currentUserLevel = $this->level(RoleEnum::tryFrom($role));

        return $currentUserLevel;
    }

    public function check(RoleEnum $minRole, ?User $user = null): bool
    {
        $userLevel = $this->getCurrentUserLevel($user);
        $requiredLevel = $this->level($minRole);

        return $userLevel >= $requiredLevel;
    }

    public function getUser(): ?User
    {
        $user = $this->security->getUser();
        return ($user instanceof User) ? $user : null;
    }
}