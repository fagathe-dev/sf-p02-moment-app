<?php

namespace App\Utils\Mailer\Service;

use App\Utils\Mailer\Enum\EmailTypeEnum;
use App\Utils\Mailer\Model\Email;

final class EmailMockFactory
{
    public function create(EmailTypeEnum $type): Email
    {
        $preview = true;

        $mockUser = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'username' => 'johndoe',
            'email' => 'demo@example.com',
        ];

        return match ($type) {

            EmailTypeEnum::AUTH_CONFIRMATION =>
            (new Email($type, 'Confirmation de compte'))
                ->from('no-reply@example.com', 'My App')
                ->to('demo@example.com', 'John Doe')
                ->setContext([
                    'user' => $mockUser,
                    'confirmationUrl' => 'https://example.com/register/confirm/abc123token',
                    'expires_in' => '24 heures',
                    ...compact('preview'),
                ]),

            EmailTypeEnum::AUTH_RESET_PASSWORD =>
            (new Email($type, 'Réinitialisation de mot de passe'))
                ->from('no-reply@example.com', 'My App')
                ->to('demo@example.com', 'John Doe')
                ->setContext([
                    'user' => $mockUser,
                    'reset_url' => 'https://example.com/auth/reset-password/abc123token',
                    'expires_in' => '1 heure',
                    ...compact('preview'),
                ]),
                
            EmailTypeEnum::AUTH_PROFILE_CHANGE_EMAIL =>
            (new Email($type, 'Validation de votre nouvelle adresse e-mail'))
                ->from('no-reply@example.com', 'My App')
                ->to('nouvel-email@example.com', 'John Doe')
                ->setContext([
                    'user' => $mockUser,
                    'new_email' => 'nouvel-email@example.com',
                    'confirmationUrl' => 'https://example.com/auth/profile/confirm-email/abc123token',
                    'expires_in' => '24 heures',
                    ...compact('preview'),
                ]),

            EmailTypeEnum::ADMIN_ACCOUNT_CREATED =>
            (new Email($type, 'Bienvenue - Votre compte a été créé'))
                ->from('no-reply@example.com', 'My App')
                ->to('demo@example.com', 'John Doe')
                ->setContext([
                    'user' => $mockUser,
                    'username' => 'johndoe',
                    'mail' => 'demo@example.com',
                    'password' => 'Temp@Pass123!',
                    'roles' => 'Administrateur',
                    'loginUrl' => 'https://example.com/auth/login',
                    'changePasswordUrl' => 'https://example.com/auth/reset-password/abc123token',
                    'createdBy' => 'dashboard',
                    'createdAt' => new \DateTimeImmutable(),
                    ...compact('preview'),
                ]),
        };
    }

    /** @return EmailTypeEnum[] */
    public function getAvailableTypes(): array
    {
        return EmailTypeEnum::all();
    }
}
