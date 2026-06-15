<?php

namespace App\Security\EventListener;

use App\Entity\User;
use Fagathe\CorePhp\Enum\LoggerLevelEnum;
use Fagathe\CorePhp\Trait\LoggerTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Listener de sécurité pour journaliser les événements d'authentification.
 * 
 * Écoute les événements de connexion et de déconnexion
 * pour maintenir un historique de sécurité via le système de logs JSON.
 */
final class SecurityEventListener
{
    use LoggerTrait;

    public function __construct(
        private readonly Security $security
    ) {
    }

    /**
     * Journalise les connexions réussies.
     */
    #[AsEventListener(event: 'security.interactive_login')]
    public function onInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        if (!$user instanceof User) {
            return;
        }

        $this->generateLog(
            LoggerLevelEnum::Info,
            [
                'message' => 'Connexion utilisateur réussie',
                'user_id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'ip' => $event->getRequest()->getClientIp(),
            ],
            ['action' => 'security.login.success'],
            'security/login'
        );
    }

    /**
     * Journalise les déconnexions.
     */
    #[AsEventListener(event: LogoutEvent::class)]
    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();

        if ($token === null) {
            return;
        }

        $user = $token->getUser();

        if (!$user instanceof User) {
            return;
        }

        $this->generateLog(
            LoggerLevelEnum::Info,
            [
                'message' => 'Déconnexion utilisateur',
                'user_id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'ip' => $event->getRequest()?->getClientIp(),
            ],
            ['action' => 'security.logout'],
            'security/login'
        );
    }
}
