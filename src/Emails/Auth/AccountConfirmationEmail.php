<?php

namespace App\Emails\Auth;

use App\Entity\User;
use App\Entity\UserRequest;
use App\Utils\Mailer\Enum\EmailTypeEnum;
use App\Utils\Mailer\Model\AbstractEmail;
use App\Utils\Mailer\Service\MailerService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Email de confirmation de compte utilisateur.
 * 
 * Cet email est envoyé lors de l'inscription d'un nouvel utilisateur
 * pour qu'il confirme son adresse email et active son compte.
 * 
 * @author Journal App
 */
final class AccountConfirmationEmail extends AbstractEmail
{
    private const SUBJECT = 'Confirmez votre compte';
    private const TEMPLATE = 'emails/auth/confirmation.html.twig';
    private const EXPIRATION_DELAY = '24 heures';

    public function __construct(
        private readonly MailerService $mailerService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $appName,
    ) {
        parent::__construct(EmailTypeEnum::AUTH_CONFIRMATION);
    }

    /**
     * Envoie l'email de confirmation de compte.
     * 
     * @param UserRequest $userRequest La demande de confirmation contenant le token et l'utilisateur associé
     * 
     * @return bool True si l'envoi a réussi, false en cas d'erreur
     */
    public function send(UserRequest $userRequest): bool
    {
        try {
            $user = $userRequest->getUser();

            if (!$user instanceof User) {
                return false;
            }

            // Génération de l'URL de confirmation
            $confirmationUrl = $this->urlGenerator->generate(
                'auth_registration_confirm_account',
                ['token' => $userRequest->getToken()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // Configuration du destinataire
            $fullName = trim(($user->getUsername() ?? ''));
            $recipientName = !empty($fullName) ? $fullName : $user->getUsername();
            $this->to($user->getEmail(), $recipientName);

            // Configuration de l'expéditeur (optionnel, utilise le défaut si non défini)
            $this->from(DEFAULT_EMAIL_SENDER, $this->appName);

            // Définition du contexte pour le template
            $this->setContext([
                'user' => $user,
                'confirmationUrl' => $confirmationUrl,
                'expires_in' => self::EXPIRATION_DELAY,
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
