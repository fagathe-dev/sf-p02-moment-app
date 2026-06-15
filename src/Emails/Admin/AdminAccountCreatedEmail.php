<?php

namespace App\Emails\Admin;

use App\Entity\User;
use App\Entity\UserRequest;
use App\Utils\Mailer\Enum\EmailTypeEnum;
use App\Utils\Mailer\Model\AbstractEmail;
use App\Utils\Mailer\Service\MailerService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Email de notification de création de compte administrateur.
 * 
 * Cet email est envoyé lorsqu'un administrateur est créé depuis
 * l'interface d'administration ou via la commande app:create-admin-user.
 * Il contient les identifiants de connexion en clair.
 * 
 * ⚠️ ATTENTION : Cet email contient des informations sensibles (mot de passe en clair).
 * L'utilisateur doit changer son mot de passe et supprimer définitivement cet email.
 * 
 * @author fagathe-dev
 */
final class AdminAccountCreatedEmail extends AbstractEmail
{
    private const SUBJECT = 'Votre compte administrateur a été créé';
    private const TEMPLATE = 'emails/admin/account-created.html.twig';

    public function __construct(
        private readonly MailerService $mailerService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $appName
    ) {
        parent::__construct(EmailTypeEnum::ADMIN_ACCOUNT_CREATED);
    }

    /**
     * Envoie l'email de notification de création de compte admin.
     * 
     * @param UserRequest   $userRequest   La requête utilisateur associée
     * @param string $plainPassword Le mot de passe en clair (avant hashage)
     * @param string $createdBy     Origine de la création ('command' ou 'dashboard')
     * 
     * @return bool True si l'envoi a réussi, false en cas d'erreur
     */
    public function send(UserRequest $userRequest, string $plainPassword, string $createdBy = 'dashboard'): bool
    {
        try {
            $user = $userRequest->getUser();
            // Génération de l'URL de connexion
            $loginUrl = $this->urlGenerator->generate(
                'auth_login',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // Génération de l'URL de changement de mot de passe (si existe)
            $changePasswordUrl = $this->urlGenerator->generate(
                'auth_reset_password_action',
                ['token' => $userRequest->getToken()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // Configuration du destinataire
            $fullName = trim(($user->getUsername() ?? ''));
            $recipientName = !empty($fullName) ? $fullName : $user->getUsername();
            $this->to($user->getEmail(), $recipientName);

            // Configuration de l'expéditeur
            $this->from(DEFAULT_EMAIL_SENDER, $this->appName);

            // Formater les rôles pour affichage
            $roles = $user->getRoles();
            $roleDisplay = $this->formatRoles($roles);

            // Définition du contexte pour le template
            $this->setContext([
                'user' => $user,
                'username' => $user->getUsername(),
                'mail' => $user->getEmail(),
                'password' => $plainPassword,
                'roles' => $roleDisplay,
                'loginUrl' => $loginUrl,
                'changePasswordUrl' => $changePasswordUrl,
                'createdBy' => $createdBy,
                'createdAt' => new \DateTimeImmutable(),
            ]);

            // Envoi via le service mailer
            $this->mailerService->send($this);

            return true;

        } catch (\Throwable $e) {
            // En cas d'erreur, on retourne false sans propager l'exception
            // Le logging est géré par le MailerService
            return false;
        }
    }

    /**
     * Formate les rôles pour un affichage lisible.
     * 
     * @param array $roles Les rôles de l'utilisateur
     * 
     * @return string Les rôles formatés
     */
    private function formatRoles(array $roles): string
    {
        $roleLabels = [
            'ROLE_SUPER_ADMIN' => 'Super Administrateur',
            'ROLE_ADMIN' => 'Administrateur',
            'ROLE_USER' => 'Utilisateur',
        ];

        $formatted = [];
        foreach ($roles as $role) {
            $formatted[] = $roleLabels[$role] ?? $role;
        }

        return implode(', ', $formatted);
    }

    /**
     * {@inheritdoc}
     */
    public function getSubject(): string
    {
        return self::SUBJECT;
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplate(): string
    {
        return self::TEMPLATE;
    }
}
