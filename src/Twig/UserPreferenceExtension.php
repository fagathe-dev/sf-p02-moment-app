<?php
namespace App\Twig;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class UserPreferenceExtension extends AbstractExtension
{

    public function __construct(private readonly Security $security)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction("get_preference", [$this, "getPreference"]),
        ];
    }

    public function getPreference(string $preference, mixed $default = null, ?User $user = null): mixed
    {
        if ($user === null) {
            $user = $this->getUser();
        }

        if ($user === null) {
            return $default;
        }

        return $user->getPreference($preference, $default);
    }

    private function getUser(): ?User
    {
        $user = $this->security->getUser();
        if ($user instanceof User) {
            return $user;
        }

        return null;
    }

}