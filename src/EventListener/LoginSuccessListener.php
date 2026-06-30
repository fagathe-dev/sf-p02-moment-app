<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginSuccessListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly UserService $userService,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        // On s'assure qu'il s'agit bien de notre entité User
        if (!$user instanceof User) {
            return;
        }

        // On génère le nouveau token UUID v4 natif pour la session du Vault
        $newToken = $this->userService->generateStandardToken();
        
        // On l'assigne à la propriété correcte de l'entité
        $user->setVaultTokenSession($newToken);

        // On sauvegarde en base de données
        $this->entityManager->flush();
    }
}