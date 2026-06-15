<?php
namespace App\Emails\Auth;

use App\Entity\User;
use App\Entity\UserRequest;
use App\Utils\Mailer\Enum\EmailTypeEnum;
use App\Utils\Mailer\Model\AbstractEmail;
use App\Utils\Mailer\Service\MailerService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ProfileChangeEmailEmail extends AbstractEmail
{
    private const SUBJECT = 'Confirmez votre nouvelle adresse e-mail';
    private const TEMPLATE = 'emails/auth/profile-change-email.html.twig';
    private const EXPIRATION_DELAY = '24 heures';

    public function __construct(
        private readonly MailerService $mailerService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $appName,
    ) {
        // ATTENTION: Il faudra ajouter AUTH_PROFILE_CHANGE_EMAIL dans ton EmailTypeEnum !
        parent::__construct(EmailTypeEnum::AUTH_PROFILE_CHANGE_EMAIL);
    }

    public function send(UserRequest $userRequest): bool
    {
        try {
            $user = $userRequest->getUser();
            $content = $userRequest->getContent();

            if (!$user instanceof User || !isset($content['new_email'])) {
                return false;
            }

            $newEmail = $content['new_email'];

            $confirmationUrl = $this->urlGenerator->generate(
                'auth_profile_confirm_email',
                ['token' => $userRequest->getToken()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // On envoie à la NOUVELLE adresse !
            $this->to($newEmail, $user->getUsername() ?? '');
            $this->from(DEFAULT_EMAIL_SENDER, $this->appName);

            $this->setContext([
                'user' => $user,
                'new_email' => $newEmail,
                'confirmationUrl' => $confirmationUrl,
                'expires_in' => self::EXPIRATION_DELAY,
            ]);

            $this->mailerService->send($this);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getSubject(): string
    {
        return self::SUBJECT;
    }

    public function getTemplate(): string
    {
        return self::TEMPLATE;
    }
}