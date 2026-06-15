<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\UserRequest;
use App\Service\UserRequest\UserRequestTypeEnum;
use App\Utils\Mailer\Enum\EmailTypeEnum;
use App\Utils\Mailer\Model\Email;
use App\Utils\Mailer\Service\MailerService;
use Fagathe\CorePhp\Logger\Logger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande pour tester l'envoi d'emails via le MailerService.
 * 
 * Permet de vérifier la configuration SMTP et d'envoyer des emails de test
 * avec des données mock sans rien sauvegarder en base de données.
 * 
 * @author Journal App
 */
#[AsCommand(
    name: 'app:test-mailer',
    description: 'Teste l\'envoi d\'email de confirmation avec des données mock',
)]
class TestMailerCommand extends Command
{
    private ?Logger $logger = null;

    public function __construct(
        private readonly MailerService $mailerService,
        private readonly string $appName,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'Adresse email de destination', 'contact@frederickagathe.fr')
            ->addOption('count', 'c', InputOption::VALUE_OPTIONAL, 'Nombre d\'emails à envoyer', 1)
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'Type d\'email à envoyer (confirmation, welcome, password_reset)', 'confirmation')
            ->setHelp(<<<'HELP'
La commande <info>%command.name%</info> permet de tester l'envoi d'emails.

<info>php %command.full_name%</info>
<info>php %command.full_name% test@example.com</info>
<info>php %command.full_name% test@example.com --count=3</info>
<info>php %command.full_name% test@example.com --type=welcome</info>

Types d'emails disponibles:
  - <comment>confirmation</comment> : Email de confirmation de compte (par défaut)
  - <comment>welcome</comment> : Email de bienvenue
  - <comment>password_reset</comment> : Email de réinitialisation de mot de passe
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->logger = new Logger('command/test-mailer');

        $targetEmail = $input->getArgument('email');
        $count = (int) $input->getOption('count');
        $type = $input->getOption('type');

        $io->title('Test d\'envoi d\'email');

        $io->text([
            sprintf('📧 Email de destination : <info>%s</info>', $targetEmail),
            sprintf('🔢 Nombre d\'envois : <info>%d</info>', $count),
            sprintf('📝 Type d\'email : <info>%s</info>', $type),
        ]);

        $io->newLine();

        $this->logger->info([
            'message' => 'Commande test-mailer démarrée',
            'target_email' => $targetEmail,
            'count' => $count,
            'type' => $type,
        ], ['action' => 'command.start']);

        $successCount = 0;
        $failCount = 0;

        $io->progressStart($count);

        for ($i = 1; $i <= $count; $i++) {
            // Générer les données mock
            $user = $this->generateMockUser($targetEmail);
            $userRequest = $this->generateMockUserRequest($user);

            try {
                $this->sendEmail($type, $user, $userRequest);
                $successCount++;

                $this->logger->info([
                    'message' => 'Email envoyé avec succès',
                    'recipient' => $user->getEmail(),
                    'type' => $type,
                    'email_number' => $i,
                ], ['action' => 'email.sent.success']);

            } catch (\Throwable $e) {
                $failCount++;

                $this->logger->error([
                    'message' => 'Échec de l\'envoi de l\'email',
                    'recipient' => $user->getEmail(),
                    'type' => $type,
                    'email_number' => $i,
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ], ['action' => 'email.sent.error']);

                $io->error(sprintf('Email #%d - Erreur: %s', $i, $e->getMessage()));
            }

            $io->progressAdvance();

            // Pause entre les envois pour éviter le rate limiting
            if ($i < $count) {
                usleep(500000); // 0.5 seconde
            }
        }

        $io->progressFinish();
        $io->newLine();

        // Résumé
        $io->section('Résumé');
        $io->table(
            ['Métrique', 'Valeur'],
            [
                ['Total', $count],
                ['Succès', sprintf('<fg=green>%d</>', $successCount)],
                ['Échecs', sprintf('<fg=red>%d</>', $failCount)],
            ]
        );

        // Log final
        $this->logger->info([
            'message' => 'Commande test-mailer terminée',
            'total' => $count,
            'success' => $successCount,
            'failures' => $failCount,
        ], ['action' => 'command.complete']);

        if ($failCount === 0) {
            $io->success(sprintf('✅ %d email(s) envoyé(s) avec succès !', $successCount));
            return Command::SUCCESS;
        } elseif ($successCount === 0) {
            $io->error('❌ Aucun email n\'a pu être envoyé. Vérifiez votre configuration MAILER_DSN.');
            return Command::FAILURE;
        } else {
            $io->warning(sprintf('⚠️ %d email(s) envoyé(s), %d échec(s).', $successCount, $failCount));
            return Command::FAILURE;
        }
    }

    private function sendEmail(string $type, User $user, UserRequest $userRequest): void
    {
        $confirmationUrl = 'https://example.com/confirm-account?token=' . $userRequest->getToken();
        $resetUrl = 'https://example.com/reset-password?token=' . $userRequest->getToken();

        $email = match ($type) {
            'confirmation' => (new Email(EmailTypeEnum::AUTH_CONFIRMATION, 'Confirmez votre compte ' . $this->appName))
                ->from(DEFAULT_EMAIL_SENDER, $this->appName)
                ->to($user->getEmail(), trim($user->getUsername()))
                ->setContext([
                    'user' => $user,
                    'confirmationUrl' => $confirmationUrl,
                    'expires_in' => '24 heures',
                ]),

            'password_reset' => (new Email(EmailTypeEnum::AUTH_RESET_PASSWORD, 'Réinitialisation de votre mot de passe'))
                ->from(DEFAULT_EMAIL_SENDER, $this->appName)
                ->to($user->getEmail(), trim($user->getUsername()))
                ->setContext([
                    'user' => $user,
                    'reset_link' => $resetUrl,
                    'username' => $user->getUsername(),
                    'expires_in' => '1 heure',
                ]),

            default => throw new \InvalidArgumentException(sprintf('Type d\'email "%s" non supporté.', $type)),
        };

        $this->mailerService->send($email);
    }

    private function generateMockUser(string $email): User
    {
        static $counter = 0;
        $counter++;

        $firstNames = ['Jean', 'Marie', 'Pierre', 'Sophie', 'Thomas', 'Camille', 'Lucas', 'Emma', 'Hugo', 'Léa'];
        $lastNames = ['Dupont', 'Martin', 'Bernard', 'Petit', 'Durand', 'Leroy', 'Moreau', 'Simon', 'Laurent', 'Michel'];
        $lastName = $lastNames[array_rand($lastNames)];
        $firstName = $firstNames[array_rand($firstNames)];

        $username = strtolower(substr($firstName, 0, 1) . $lastName . $counter);

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setIsVerified(false);
        $user->setRoles(['ROLE_USER']);

        return $user;
    }

    private function generateMockUserRequest(User $user): UserRequest
    {
        $token = bin2hex(random_bytes(20)); // 40 caractères

        $userRequest = new UserRequest();
        $userRequest->setType(UserRequestTypeEnum::AUTH_ACCOUNT_VERIFICATION);
        $userRequest->setToken($token);
        $userRequest->setCreatedAt(new \DateTimeImmutable());
        $userRequest->setExpiresAt(new \DateTimeImmutable('+24 hours'));
        $userRequest->setIsUsed(false);
        $userRequest->setUser($user);

        return $userRequest;
    }
}
