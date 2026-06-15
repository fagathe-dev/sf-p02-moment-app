<?php

namespace App\Service;

use App\Emails\Admin\AdminAccountCreatedEmail;
use App\Emails\Auth\AccountConfirmationEmail;
use App\Emails\Auth\ProfileChangeEmailEmail;
use App\Entity\User;
use App\Entity\UserRequest;
use App\Repository\UserRepository;
use App\Security\Authenticator\FormLoginAuthenticator;
use App\Security\Enum\RoleEnum;
use App\Service\UserRequest\UserRequestService;
use App\Service\UserRequest\UserRequestTypeEnum;
use Doctrine\ORM\EntityManagerInterface;
use Fagathe\CorePhp\Breadcrumb\Breadcrumb;
use Fagathe\CorePhp\Breadcrumb\BreadcrumbItem;
use Fagathe\CorePhp\Uploader\FileUploadException;
use Fagathe\CorePhp\Uploader\UploaderService;
use Fagathe\CorePhp\Uploader\UploaderValidationService;
use Fagathe\CorePhp\Uploader\UploadResult;
use Fagathe\CorePhp\Enum\LoggerLevelEnum;
use Fagathe\CorePhp\Generator\TokenGenerator;
use Fagathe\CorePhp\Trait\DatetimeTrait;
use Fagathe\CorePhp\Trait\LoggerTrait;
use Fagathe\CorePhp\Trait\PaginationTrait;
use Fagathe\CorePhp\Trait\SessionFlashTrait;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

/**
 * Service for managing user accounts.
 * 
 * Handles user registration, profile updates, and user data retrieval.
 * Provides business logic layer above the repository for user management operations.
 * 
 * @author fagathe-dev <https://github.com/fagathe-dev/>
 */
final class UserService
{

    private readonly Filesystem $filesystem;

    use LoggerTrait, DatetimeTrait, SessionFlashTrait, PaginationTrait;

    /**
     * @param UserRepository                   $repository Repository for user data access
     * @param UserPasswordHasherInterface      $hasher     Password hashing service
     * @param TokenGenerator                   $tokenGenerator Générateur de tokens
     * @param Security                         $security   Security service
     * @param EntityManagerInterface           $entityManager Gestionnaire d'entités
     * @param SerializerInterface              $serializer    Service de sérialisation
     * @param UrlGeneratorInterface            $urlGenerator  Générateur d'URL
     * @param AccountConfirmationEmail         $accountConfirmationEmail Service d'envoi d'email de confirmation
     */
    public function __construct(
        private readonly UserRepository $repository,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly TokenGenerator $tokenGenerator,
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly SerializerInterface $serializer,
        private readonly UrlGeneratorInterface $urlGenerator,
        protected readonly PaginatorInterface $paginator,
        private readonly AccountConfirmationEmail $accountConfirmationEmail,
        private readonly AdminAccountCreatedEmail $adminAccountCreatedEmail,
        private readonly UserRequestService $userRequestService,
        private readonly UploaderService $uploaderService,
        private readonly UploaderValidationService $uploaderValidationService,
        private readonly ProfileChangeEmailEmail $profileChangeEmailEmail, // 👈 Ajout
        private readonly string $projectDir,
    ) {
        $this->filesystem = new Filesystem;
    }

    /**
     * Enregistre un nouvel utilisateur.
     * 
     * Crée un nouveau compte utilisateur en hashant le mot de passe
     * et en définissant les propriétés par défaut.
     * 
     * @param User $user L'utilisateur à enregistrer
     * 
     * @return bool True si l'enregistrement a réussi, false en cas d'erreur
     */
    public function register(User $user): bool
    {
        $now = $this->now();

        // Création d'une demande de confirmation de compte
        $userRequest = new UserRequest();
        $userRequest->setType(UserRequestTypeEnum::AUTH_ACCOUNT_VERIFICATION)
            ->setToken($this->tokenGenerator->generate(40))
            ->setExpiresAt($this->modifyDateTime('+24 hours', $now))
            ->setCreatedAt($now)
            ->setIsUsed(false);

        $user->addUserRequest($userRequest);
        $user->setRoles([RoleEnum::ROLE_USER->value])
            ->setIsVerified(false);

        try {
            // Sauvegarde de l'utilisateur avec le UserRequest
            $this->saveUser($user, true);

            // Envoi de l'email de confirmation
            $emailSent = $this->accountConfirmationEmail->send($userRequest);

            if ($emailSent) {
                $this->generateLog(
                    LoggerLevelEnum::Info,
                    [
                        'message' => 'Utilisateur enregistré et email de confirmation envoyé',
                        'user' => $user->getUsername(),
                        'email' => $user->getEmail()
                    ],
                    ['action' => 'user.register.success_with_email']
                );
                $this->addFlash('success', 'Votre compte a été créé avec succès ! Rendez-vous dans votre boîte email pour confirmer votre compte.');
            } else {
                $this->generateLog(
                    LoggerLevelEnum::Warning,
                    [
                        'message' => 'Utilisateur enregistré mais email de confirmation non envoyé',
                        'user' => $user->getUsername(),
                        'email' => $user->getEmail()
                    ],
                    ['action' => 'user.register.email_not_sent']
                );
                $this->addFlash('warning', 'Votre compte a été créé mais l\'email de confirmation n\'a pas pu être envoyé. Veuillez contacter le support.');
            }

            return true;

        } catch (Throwable $e) {
            // Erreur critique - échec de l'inscription
            $this->generateLog(
                LoggerLevelEnum::Critical,
                [
                    'message' => 'Erreur critique lors de l\'inscription',
                    'user' => $user->getUsername(),
                    'error' => $e->getMessage()
                ],
                ['action' => 'user.register.critical_error']
            );
            $this->addFlash('danger', 'Une erreur est survenue lors de la création de votre compte. Veuillez réessayer.');
            return false;
        }
    }

    /**
     * Confirme un compte utilisateur via le token de vérification.
     * Délègue la logique au UserRequestService.
     * 
     * @param string $token Le token de confirmation
     * 
     * @return bool True si la confirmation a réussi
     */
    public function confirmAccount(string $token): bool
    {
        return $this->userRequestService->confirmAccount($token);
    }

    /**
     * @param User $user
     * 
     * @return bool
     */
    public function createUser(User $user): bool
    {

        try {
            $userRequest = $this->userRequestService->createUserRequest(
                UserRequestTypeEnum::AUTH_PASSWORD_RESET
            );

            $plainPassword = $this->tokenGenerator->generate(12);
            $user->setPassword($plainPassword);
            $user = $this->hashPassword($user);

            $user->addUserRequest($userRequest);

            $this->adminAccountCreatedEmail->send($userRequest, $plainPassword);

            $this->saveUser($user, true);
            return true;
        } catch (Throwable $th) {
            $this->generateLog(
                LoggerLevelEnum::Error,
                [
                    'message' => 'Erreur lors de la création de l\'utilisateur',
                    'user' => $user->getUsername(),
                    'error' => $th->getMessage()
                ],
                ['action' => 'user.create.error']
            );
            return false;
        }
    }

    /**
     * Met à jour un utilisateur existant.
     * 
     * Persiste les modifications d'un utilisateur en base de données
     * en mettant à jour la date de modification.
     * 
     * @param User $user L'utilisateur à mettre à jour
     * 
     * @return bool True si la mise à jour a réussi, false en cas d'erreur
     */
    public function update(User $user): bool
    {
        try {
            $this->saveUser($user, false);
            return true;
        } catch (Throwable $th) {
            $this->generateLog(
                LoggerLevelEnum::Error,
                [
                    'message' => 'Erreur lors de la mise à jour de l\'utilisateur',
                    'user' => $user->getUsername(),
                    'error' => $th->getMessage()
                ],
                ['action' => 'user.update.error']
            );
            return false;
        }
    }

    public function manageUsers(Request $request): array
    {
        $page = (int) $request->query->get('p', 1);
        $limit = (int) $request->query->get('nbUsers', 20);

        $paginatedUsers = $this->getPaginatedUsers($page, $limit);
        $breadcrumb = $this->breadcrumb();

        return compact('paginatedUsers', 'breadcrumb');
    }

    /**
     * Trouve un utilisateur par son identifiant.
     * 
     * @param int $id L'identifiant de l'utilisateur
     * 
     * @return User|null L'utilisateur trouvé ou null si non trouvé
     */
    public function findById(int $id): ?User
    {
        return $this->repository->find($id);
    }

    /**
     * Trouve un utilisateur par son adresse email.
     * 
     * Les espaces en début et fin d'email sont automatiquement supprimés.
     * 
     * @param string $email L'adresse email de l'utilisateur
     * 
     * @return User|null L'utilisateur trouvé ou null si non trouvé
     */
    public function findByEmail(string $email): ?User
    {
        return $this->repository->findOneBy(['email' => trim($email)]);
    }

    /**
     * Trouve un utilisateur par son nom d'utilisateur.
     * 
     * Les espaces en début et fin du nom d'utilisateur sont automatiquement supprimés.
     * 
     * @param string $username Le nom d'utilisateur
     * 
     * @return User|null L'utilisateur trouvé ou null si non trouvé
     */
    public function findByUsername(string $username): ?User
    {
        return $this->repository->findOneBy(['username' => trim($username)]);
    }

    public function findAllUsers(): array
    {
        return $this->repository->findBy([], ['registeredAt' => 'DESC']);
    }

    /**
     * Récupère une liste paginée d'utilisateurs.
     * 
     * Exemple d'utilisation de la méthode générique de pagination
     * pour récupérer les utilisateurs avec tri et filtrage.
     * 
     * @param int    $page     Le numéro de page (commence à 1)
     * @param int    $limit    Le nombre d'utilisateurs par page (défaut: 20)
     * @param string $orderBy  Champ de tri (défaut: 'registeredAt')
     * @param string $order    Direction du tri (ASC|DESC, défaut: 'DESC')
     * 
     * @return PaginationInterface Objet de pagination
     */
    public function getPaginatedUsers(int $page = 1, int $limit = 20, string $orderBy = 'created_at', string $order = 'DESC'): PaginationInterface
    {
        // Création du QueryBuilder pour les utilisateurs
        $queryBuilder = $this->repository->createQueryBuilder('u')
            ->orderBy("u.{$orderBy}", $order);

        // Options de pagination pour les utilisateurs
        $options = [
            'defaultSortFieldName' => "u.{$orderBy}",
            'defaultSortDirection' => $order,
            'sortFieldWhitelist' => ['u.username', 'u.email', 'u.created_at', 'u.updated_at'],
            'filterFieldWhitelist' => ['u.username', 'u.email']
        ];

        // Contexte de log
        $logContext = [
            'entity_type' => 'User',
            'order_by' => $orderBy,
            'order_direction' => $order
        ];

        // Utilisation de la méthode générique de pagination
        return $this->paginate(
            $queryBuilder,
            $page,
            $limit,
            $options,
            'user.paginated.retrieve',
            $logContext
        );
    }

    /**
     * Hash le mot de passe d'un utilisateur.
     * 
     * Remplace le mot de passe en texte clair par sa version hashée
     * en utilisant l'algorithme configuré dans Symfony.
     * 
     * @param User $user L'utilisateur dont on veut hasher le mot de passe
     * 
     * @return User L'utilisateur avec le mot de passe hashé
     */
    public function hashPassword(User $user): User
    {
        return $user->setPassword(
            $this->hasher->hashPassword($user, $user->getPassword())
        );
    }

    /**
     * Sauvegarde un utilisateur en base de données.
     * 
     * Gère automatiquement les dates de création/mise à jour et 
     * les propriétés par défaut selon le type d'opération.
     * 
     * @param User $user       L'utilisateur à sauvegarder
     * @param bool $isCreation True pour une création, false pour une mise à jour
     * 
     * @return bool
     */
    public function saveUser(User $user, bool $isCreation = false): bool
    {
        return $this->repository->save($user, true, $isCreation);
    }

    /**
     * @param int $id
     * 
     * @return bool
     */
    public function deleteUser(int $id): bool
    {
        $user = $this->repository->find($id);
        if ($user === null) {
            $this->generateLog(
                LoggerLevelEnum::Warning,
                [
                    'message' => 'Utilisateur introuvable pour la suppression',
                    'user_id' => $id
                ],
                ['action' => 'admin.user.delete.not_found']
            );
            return false;
        }

        try {
            $this->repository->remove($user, true);
            $this->deleteAvatar($user);

            $this->generateLog(
                LoggerLevelEnum::Info,
                [
                    'message' => 'Utilisateur supprimé avec succès',
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail()
                ],
                ['action' => 'admin.user.delete.success']
            );

            return true;
        } catch (Throwable $th) {
            $this->generateLog(
                LoggerLevelEnum::Error,
                [
                    'message' => 'Erreur lors de la suppression de l\'utilisateur',
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'error' => $th->getMessage()
                ],
                ['action' => 'admin.user.delete.error']
            );

            return false;
        }
    }

    /**
     * Génère le fil d'Ariane pour la gestion des utilisateurs.
     * @param BreadcrumbItem[] $items Les éléments du fil d'Ariane
     * 
     * @return Breadcrumb
     */
    public function breadcrumb(array $items = []): Breadcrumb
    {
        // Implémentation spécifique pour UserService
        return new Breadcrumb([
            new BreadcrumbItem(name: 'Gestion des utilisateurs', link: $this->urlGenerator->generate('admin_user_index')),
            ...$items
        ]);
    }

    public function getCurrentUser(): ?User
    {
        $user = $this->security->getUser();

        if ($user instanceof User) {
            return $user;
        }

        return null;
    }

    public function updateAvatar(User $user, UploadedFile $file): UploadResult
    {
        $this->uploaderValidationService
            ->setAllowedMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->setMaxSize(15 * 1024 * 1024); // 15MB

        $validation = $this->uploaderValidationService->validate($file);

        if ($validation !== true) {
            throw new FileUploadException(implode(' ', (array) $validation));
        }

        $result = $this->uploaderService
            ->setUploadDirectory('avatars')
            ->upload($file, $user->getAvatar());

        $user->setAvatar($result->relativePath);
        $this->entityManager->flush();

        return $result;
    }

    private function deleteAvatar(?User $user = null): void
    {
        $user = $user ?? $this->getCurrentUser();
        if ($user instanceof User) {
            if ($user->getAvatar()) {
                $avatarPath = $this->projectDir . '/' . PUBLIC_DIR . '/' . $user->getAvatar();
                if ($this->filesystem->exists($avatarPath)) {
                    $this->filesystem->remove($avatarPath);
                }
            }
        }
    }

    // 2. Ajoute la méthode de traitement de la demande :
    public function requestEmailChange(User $user, string $newEmail): bool
    {
        try {
            // Création de la demande
            $userRequest = $this->userRequestService->createUserRequest(UserRequestTypeEnum::AUTH_PROFILE_CHANGE_EMAIL);

            // 🔥 On stocke le nouvel email dans le JSON
            $userRequest->setContent(['new_email' => $newEmail]);

            $user->addUserRequest($userRequest);

            // Sauvegarde en BDD
            $this->saveUser($user, false);

            // Envoi du mail
            $emailSent = $this->profileChangeEmailEmail->send($userRequest);

            if ($emailSent) {
                $this->generateLog(LoggerLevelEnum::Info, [
                    'message' => 'Demande de changement d\'e-mail initiée',
                    'user_id' => $user->getId(),
                    'new_email' => $newEmail
                ], ['action' => 'user.change_email.request_sent']);
                return true;
            }

            return false;
        } catch (Throwable $th) {
            $this->generateLog(LoggerLevelEnum::Error, [
                'message' => 'Erreur lors de la demande de changement d\'e-mail',
                'user_id' => $user->getId(),
                'error' => $th->getMessage()
            ], ['action' => 'user.change_email.request_error']);
            return false;
        }
    }

    /**
     * Rafraîchit la session de l'utilisateur pour éviter une déconnexion
     * après un changement d'identifiant (email) ou de mot de passe.
     * * @param User $user L'utilisateur dont on veut maintenir la session
     */
    public function refreshSession(User $user): void
    {
        $this->security->login($user, FormLoginAuthenticator::class, 'main');

        $this->generateLog(LoggerLevelEnum::Info, [
            'message' => 'Session rafraîchie automatiquement',
            'user_id' => $user->getId(),
        ], ['action' => 'user.session.refreshed']);
    }
}