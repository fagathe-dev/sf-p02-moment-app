<?php

namespace App\Command;

use App\Emails\Admin\AdminAccountCreatedEmail;
use App\Entity\User;
use App\Entity\UserRequest;
use App\Service\UserRequest\UserRequestService;
use App\Service\UserRequest\UserRequestTypeEnum;
use App\Service\UserService;
use DateTimeImmutable;
use Fagathe\CorePhp\Enum\LoggerLevelEnum;
use Fagathe\CorePhp\Generator\TokenGenerator;
use Fagathe\CorePhp\Logger\Logger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Commande pour créer un utilisateur administrateur.
 * 
 * Permet de créer un utilisateur avec le rôle ROLE_ADMIN ou ROLE_SUPER_ADMIN
 * directement depuis la ligne de commande.
 * 
 * Compatible PHP 7.3.7 et PHP 8.3+
 * 
 * @author @fagathe-dev <https://github.com/fagathe-dev>
 */
class CreateAdminUserCommand extends Command
{
    /** @var string */
    protected static $defaultName = 'app:create-admin-user';

    /** @var string */
    protected static $defaultDescription = 'Créer un utilisateur administrateur (ROLE_ADMIN ou ROLE_SUPER_ADMIN)';

    // Constantes globales chargées via Composer (autoload.files)

    private const TYPE_ADMIN = 'admin';
    private const TYPE_SUPER_ADMIN = 'super_admin';

    private const ROLE_MAPPING = [
        self::TYPE_ADMIN => 'ROLE_ADMIN',
        self::TYPE_SUPER_ADMIN => 'ROLE_SUPER_ADMIN',
    ];

    /** @var Logger|null */
    private $logger = null;

    /**
     * @param UserService              $userService              Service de gestion des utilisateurs
     * @param UserRequestService       $userRequestService       Service de gestion des requêtes utilisateur
     * @param Security                 $security                 Service de sécurité Symfony
     * @param AdminAccountCreatedEmail $adminAccountCreatedEmail Service d'envoi d'email de création de compte admin
     */
    public function __construct(
        private UserService $userService,
        private UserRequestService $userRequestService,
        private Security $security,
        private AdminAccountCreatedEmail $adminAccountCreatedEmail
    ) {
        parent::__construct();
    }

    /**
     * Configure les arguments et options de la commande.
     * 
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName(self::$defaultName)
            ->setDescription(self::$defaultDescription)
            ->addArgument('username', InputArgument::OPTIONAL, 'Nom d\'utilisateur de l\'admin')
            ->addArgument('email', InputArgument::OPTIONAL, 'Adresse e-mail de l\'admin')
            ->addArgument('password', InputArgument::OPTIONAL, 'Mot de passe de l\'admin')
            ->addOption(
                'type',
                't',
                InputOption::VALUE_REQUIRED,
                'Type d\'administrateur : "admin" (ROLE_ADMIN) ou "super_admin" (ROLE_SUPER_ADMIN)',
                self::TYPE_SUPER_ADMIN
            )
            ->setHelp(
                <<<'HELP'
Cette commande permet de créer un utilisateur administrateur.

Utilisation :
  php bin/console app:create-admin-user [username] [email] [password] --type=<admin|super_admin>

Options :
  --type (-t)   Type d'administrateur : "admin" ou "super_admin" (défaut: super_admin)

Exemples :
  # Mode interactif (recommandé pour saisir le mot de passe de façon sécurisée)
  php bin/console app:create-admin-user
  
  # Création d'un super admin
  php bin/console app:create-admin-user john john@example.com mypassword --type=super_admin
  
  # Création d'un admin simple
  php bin/console app:create-admin-user jane jane@example.com mypassword --type=admin
HELP
            );
    }

    /**
     * Exécute la commande de création d'utilisateur admin.
     * 
     * @param InputInterface  $input  Interface d'entrée
     * @param OutputInterface $output Interface de sortie
     * 
     * @return int Code de retour (0 = succès, 1 = erreur)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initLogger();
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        try {
            // Validation et récupération du type d'admin
            $type = $this->getAdminType($input, $output, $helper, $io);
            if ($type === null) {
                return Command::FAILURE;
            }

            // Récupération des informations utilisateur
            $username = $this->getUsername($input, $output, $helper);
            $email = $this->getEmail($input, $output, $helper);
            $password = $this->getPassword($input, $output, $helper);

            // Validation des données
            if (!$this->validateUserData($username, $email, $password, $io)) {
                return Command::FAILURE;
            }

            // Stocker le mot de passe en clair avant hashage (pour l'email)
            $plainPassword = $password;

            // Création de l'utilisateur
            $user = $this->createUser($username, $email, $password, $type);
            $userRequest = $this->userRequestService->createUserRequest(UserRequestTypeEnum::AUTH_PASSWORD_RESET);
            $user->addUserRequest($userRequest);

            // Vérification des doublons
            if (!$this->checkNoDuplicate($user, $io)) {
                return Command::FAILURE;
            }

            // Sauvegarde
            if (!$this->saveUser($user, $io)) {
                return Command::FAILURE;
            }

            // Envoi de l'email avec les identifiants
            $emailSent = $this->sendAdminCreatedEmail($userRequest, $plainPassword, $io);

            $roleName = self::ROLE_MAPPING[$type];
            $successMessage = sprintf(
                'Utilisateur administrateur "%s" créé avec succès ! 🚀' . PHP_EOL .
                'Rôle : %s' . PHP_EOL .
                'Email : %s',
                $username,
                $roleName,
                $email
            );

            if ($emailSent) {
                $successMessage .= PHP_EOL . '📧 Email avec les identifiants envoyé à ' . $email;
            } else {
                $successMessage .= PHP_EOL . '⚠️ L\'email avec les identifiants n\'a pas pu être envoyé.';
            }

            $io->success($successMessage);

            $this->log(
                LoggerLevelEnum::Info,
                [
                    'message' => 'Utilisateur administrateur créé via commande',
                    'username' => $username,
                    'email' => $email,
                    'role' => $roleName
                ],
                [
                    'action' => 'command.create_admin_user.success',
                    'origin' => 'cli.app:create-admin-user'
                ]
            );

            return Command::SUCCESS;

        } catch (Throwable $e) {
            $io->error('Erreur lors de la création de l\'utilisateur : ' . $e->getMessage());

            $this->log(
                LoggerLevelEnum::Error,
                [
                    'message' => 'Erreur lors de la création d\'un admin via commande',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ],
                [
                    'action' => 'command.create_admin_user.error',
                    'origin' => 'cli.app:create-admin-user'
                ]
            );

            return Command::FAILURE;
        }
    }

    /**
     * Récupère et valide le type d'administrateur.
     * 
     * @param InputInterface  $input  Interface d'entrée
     * @param OutputInterface $output Interface de sortie
     * @param mixed           $helper Helper de questions
     * @param SymfonyStyle    $io     Interface de style
     * 
     * @return string|null Type validé ou null en cas d'erreur
     */
    private function getAdminType(InputInterface $input, OutputInterface $output, $helper, SymfonyStyle $io)
    {
        $type = $input->getOption('type');

        if (!in_array($type, [self::TYPE_ADMIN, self::TYPE_SUPER_ADMIN], true)) {
            $io->warning(sprintf('Type invalide "%s". Types valides : admin, super_admin', $type));

            $question = new ChoiceQuestion(
                'Choisissez le type d\'administrateur :',
                [self::TYPE_SUPER_ADMIN, self::TYPE_ADMIN],
                0
            );

            $type = $helper->ask($input, $output, $question);
        }

        return $type;
    }

    /**
     * Récupère le nom d'utilisateur.
     * 
     * @param InputInterface  $input  Interface d'entrée
     * @param OutputInterface $output Interface de sortie
     * @param mixed           $helper Helper de questions
     * 
     * @return string Nom d'utilisateur
     */
    private function getUsername(InputInterface $input, OutputInterface $output, $helper)
    {
        $username = $input->getArgument('username');

        if (!$username) {
            $question = new Question('Nom d\'utilisateur : ');
            $question->setValidator(function ($value) {
                if (empty(trim($value))) {
                    throw new \RuntimeException('Le nom d\'utilisateur ne peut pas être vide.');
                }
                return $value;
            });
            $username = $helper->ask($input, $output, $question);
        }

        return trim($username);
    }

    /**
     * Récupère l'adresse e-mail.
     * 
     * @param InputInterface  $input  Interface d'entrée
     * @param OutputInterface $output Interface de sortie
     * @param mixed           $helper Helper de questions
     * 
     * @return string Adresse e-mail
     */
    private function getEmail(InputInterface $input, OutputInterface $output, $helper)
    {
        $email = $input->getArgument('email');

        if (!$email) {
            $question = new Question('Adresse e-mail : ');
            $question->setValidator(function ($value) {
                if (empty(trim($value))) {
                    throw new \RuntimeException('L\'adresse e-mail ne peut pas être vide.');
                }
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('L\'adresse e-mail n\'est pas valide.');
                }
                return $value;
            });
            $email = $helper->ask($input, $output, $question);
        }

        return trim($email);
    }

    /**
     * Récupère le mot de passe.
     * 
     * @param InputInterface  $input  Interface d'entrée
     * @param OutputInterface $output Interface de sortie
     * @param mixed           $helper Helper de questions
     * 
     * @return string Mot de passe
     */
    private function getPassword(InputInterface $input, OutputInterface $output, $helper)
    {
        $password = $input->getArgument('password');

        if (!$password) {
            $question = new Question('Mot de passe : ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $question->setValidator(function ($value) {
                if (empty($value)) {
                    throw new \RuntimeException('Le mot de passe ne peut pas être vide.');
                }
                if (strlen($value) < 8) {
                    throw new \RuntimeException('Le mot de passe doit contenir au moins 8 caractères.');
                }
                return $value;
            });
            $password = $helper->ask($input, $output, $question);
        }

        return $password;
    }

    /**
     * Valide les données utilisateur.
     * 
     * @param string       $username Nom d'utilisateur
     * @param string       $email    Adresse e-mail
     * @param string       $password Mot de passe
     * @param SymfonyStyle $io       Interface de style
     * 
     * @return bool True si valide, false sinon
     */
    private function validateUserData($username, $email, $password, SymfonyStyle $io)
    {
        $errors = [];

        if (empty(trim($username))) {
            $errors[] = 'Le nom d\'utilisateur ne peut pas être vide.';
        }

        if (empty(trim($email)) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'L\'adresse e-mail n\'est pas valide.';
        }

        if (empty($password) || strlen($password) < 8) {
            $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        }

        if (!empty($errors)) {
            $io->error($errors);

            $this->log(
                LoggerLevelEnum::Warning,
                [
                    'message' => 'Validation échouée lors de la création d\'admin',
                    'errors' => $errors
                ],
                [
                    'action' => 'command.create_admin_user.validation_failed',
                    'origin' => 'cli.app:create-admin-user'
                ]
            );

            return false;
        }

        return true;
    }

    /**
     * Crée l'entité User avec les données fournies.
     * 
     * @param string $username Nom d'utilisateur
     * @param string $email    Adresse e-mail
     * @param string $password Mot de passe
     * @param string $type     Type d'administrateur
     * 
     * @return User Instance de User configurée
     */
    private function createUser($username, $email, $password, $type)
    {
        $user = new User();
        $role = self::ROLE_MAPPING[$type];

        $user
            ->setEmail($email)
            ->setUsername($username)
            ->setPassword($password)
            ->setRoles([$role])
            ->setIsVerified(true)
            ->setCreatedAt(new DateTimeImmutable());

        return $user;
    }

    /**
     * Vérifie qu'aucun utilisateur avec le même username ou email n'existe déjà.
     * 
     * @param User         $user Utilisateur à vérifier
     * @param SymfonyStyle $io   Interface de style
     * 
     * @return bool True si pas de doublon, false sinon
     */
    private function checkNoDuplicate(User $user, SymfonyStyle $io)
    {
        $existingByUsername = $this->userService->findByUsername($user->getUsername());
        if ($existingByUsername !== null) {
            $io->error(sprintf('Un utilisateur avec le nom "%s" existe déjà.', $user->getUsername()));

            $this->log(
                LoggerLevelEnum::Warning,
                [
                    'message' => 'Tentative de création d\'admin avec username existant',
                    'username' => $user->getUsername()
                ],
                [
                    'action' => 'command.create_admin_user.duplicate_username',
                    'origin' => 'cli.app:create-admin-user'
                ]
            );

            return false;
        }

        $existingByEmail = $this->userService->findByEmail($user->getEmail());
        if ($existingByEmail !== null) {
            $io->error(sprintf('Un utilisateur avec l\'email "%s" existe déjà.', $user->getEmail()));

            $this->log(
                LoggerLevelEnum::Warning,
                [
                    'message' => 'Tentative de création d\'admin avec email existant',
                    'email' => $user->getEmail()
                ],
                [
                    'action' => 'command.create_admin_user.duplicate_email',
                    'origin' => 'cli.app:create-admin-user'
                ]
            );

            return false;
        }

        return true;
    }

    /**
     * Sauvegarde l'utilisateur en base de données.
     * 
     * @param User         $user Utilisateur à sauvegarder
     * @param SymfonyStyle $io   Interface de style
     * 
     * @return bool True si succès, false sinon
     */
    private function saveUser(User $user, SymfonyStyle $io)
    {
        // Utiliser le service pour persister directement l'utilisateur (création)
        $success = $this->userService->saveUser($user, true);

        if (!$success) {
            $io->error('Erreur lors de la sauvegarde de l\'utilisateur en base de données.');

            $this->log(
                LoggerLevelEnum::Error,
                [
                    'message' => 'Échec de sauvegarde admin en base de données',
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail()
                ],
                [
                    'action' => 'command.create_admin_user.save_failed',
                    'origin' => 'cli.app:create-admin-user'
                ]
            );

            return false;
        }

        return true;
    }

    /**
     * Envoie l'email de création de compte admin avec les identifiants.
     * 
     * @param UserRequest  $userRequest   Requête utilisateur contenant les informations
     * @param string       $plainPassword Mot de passe en clair
     * @param SymfonyStyle $io            Interface de style
     * 
     * @return bool True si envoi réussi, false sinon
     */
    private function sendAdminCreatedEmail(UserRequest $userRequest, string $plainPassword, SymfonyStyle $io): bool
    {
        try {

            $io->text('📧 Envoi de l\'email avec les identifiants...');

            $user = $userRequest->getUser();
            $emailSent = $this->adminAccountCreatedEmail->send($userRequest, $plainPassword, 'command');

            if ($emailSent) {
                $this->log(
                    LoggerLevelEnum::Info,
                    [
                        'message' => 'Email de création de compte admin envoyé',
                        'username' => $user->getUsername(),
                        'email' => $user->getEmail()
                    ],
                    [
                        'action' => 'command.create_admin_user.email_sent',
                        'origin' => 'cli.app:create-admin-user'
                    ]
                );
            } else {
                $this->log(
                    LoggerLevelEnum::Warning,
                    [
                        'message' => 'Échec de l\'envoi de l\'email de création de compte admin',
                        'username' => $user->getUsername(),
                        'email' => $user->getEmail()
                    ],
                    [
                        'action' => 'command.create_admin_user.email_failed',
                        'origin' => 'cli.app:create-admin-user'
                    ]
                );
            }

            return $emailSent;

        } catch (Throwable $e) {
            $this->log(
                LoggerLevelEnum::Error,
                [
                    'message' => 'Erreur lors de l\'envoi de l\'email de création de compte admin',
                    'username' => $user->getUsername(),
                    'error' => $e->getMessage()
                ],
                [
                    'action' => 'command.create_admin_user.email_error',
                    'origin' => 'cli.app:create-admin-user'
                ]
            );

            return false;
        }
    }

    /**
     * Initialise le logger (lazy loading).
     * 
     * @return void
     */
    private function initLogger()
    {
        if ($this->logger === null) {
            $this->logger = new Logger(
                'command/create-admin-user',
                $this->security,
                false // Pas de log IP en mode CLI
            );
        }
    }

    /**
     * Log une opération.
     * 
     * @param LoggerLevelEnum $level   Niveau de log
     * @param array           $content Contenu du log
     * @param array           $context Contexte additionnel
     * 
     * @return void
     */
    private function log($level, array $content = [], array $context = [])
    {
        try {
            if ($this->logger !== null) {
                $this->logger->log($level, $content, $context);
            }
        } catch (Throwable $e) {
            error_log('CreateAdminUserCommand Logger Error: ' . $e->getMessage());
        }
    }
}