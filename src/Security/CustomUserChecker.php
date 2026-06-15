<?php

namespace App\Security;

use App\Entity\User; // Assurez-vous d'importer votre entité User
use Fagathe\CorePhp\Enum\LoggerLevelEnum;
use Fagathe\CorePhp\Trait\LoggerTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class CustomUserChecker implements UserCheckerInterface
{
    use LoggerTrait;

    public function __construct(
        private readonly Security $security,
    ) {
    }

    /**
     * Cette méthode est appelée AVANT la vérification des identifiants (mot de passe).
     * Utilisez-la pour vérifier l'état du compte (bloqué, désactivé, etc.).
     */
    public function checkPreAuth(UserInterface $user): void
    {
        // On s'assure que l'objet est bien notre entité User (important si vous utilisez des systèmes d'authentification mixtes)
        if (!$user instanceof User) {
            return;
        }

        // ---------------------------------------------------------------------
        // Désactiver temporairement, dans l'attente de la fonctionnalité d'activation par email
        // ---------------------------------------------------------------------
        if (!$user->isVerified()) {
            // Assumant que vous avez une méthode isVerified()
            // Jetez une exception pour stopper l'authentification
            // Le message sera affiché à l'utilisateur.
            $this->generateLog(
                level: LoggerLevelEnum::Warning,
                content: [
                    'message' => 'Tentative de connexion avec un compte non vérifié',
                    'user_id' => $user->getUserIdentifier(),
                ],
                context: [
                    'action' => 'auth.login.unverified_account',
                    'user_email' => $user->getEmail(),
                ]
            );

            throw new CustomUserMessageAuthenticationException(
                'Votre compte n\'a pas encore été vérifié. Veuillez vérifier vos emails.'
            );
        }
    }

    /**
     * Cette méthode est appelée APRÈS la vérification réussie des identifiants.
     * Utilisez-la pour vérifier des conditions temporaires (mot de passe expiré, etc.).
     */
    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof User) {
            return;
        }

        // ---------------------------------------------------------------------
        // Exemple : Forcer le changement de mot de passe après une certaine période
        // ---------------------------------------------------------------------
        // if ($user->isPasswordExpired()) {
        //     throw new CredentialsExpiredException('Votre mot de passe a expiré et doit être mis à jour.');
        // }
    }

}