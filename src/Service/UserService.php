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
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * Service for managing user accounts.
 * * Handles user registration, profile updates, and user data retrieval.
 * Provides business logic layer above the repository for user management operations.
 * * @author fagathe-dev <https://github.com/fagathe-dev/>
 */
final class UserService
{

    private readonly Filesystem $filesystem;

    use LoggerTrait, DatetimeTrait, SessionFlashTrait, PaginationTrait;

    public function __construct(
        private readonly UserRepository $repository,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly TokenGenerator $tokenGenerator,
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly SerializerInterface $serializer,
        private readonly UrlGeneratorInterface $urlGenerator,
        PaginatorInterface $paginator,
        private readonly AccountConfirmationEmail $accountConfirmationEmail,
        private readonly AdminAccountCreatedEmail $adminAccountCreatedEmail,
        private readonly UserRequestService $userRequestService,
        private readonly UploaderService $uploaderService,
        private readonly UploaderValidationService $uploaderValidationService,
        private readonly ProfileChangeEmailEmail $profileChangeEmailEmail,
        private readonly string $projectDir,
    ) {
        $this->filesystem = new Filesystem;
    }

    public function register(User $user): bool
    {
        $now = $this->now();

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
            $this->saveUser($user, true);
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

    public function confirmAccount(string $token): bool
    {
        return $this->userRequestService->confirmAccount($token);
    }

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

    public function findById(int $id): ?User
    {
        return $this->repository->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->repository->findOneBy(['email' => trim($email)]);
    }

    public function findByUsername(string $username): ?User
    {
        return $this->repository->findOneBy(['username' => trim($username)]);
    }

    public function findAllUsers(): array
    {
        return $this->repository->findBy([], ['registeredAt' => 'DESC']);
    }

    public function getPaginatedUsers(int $page = 1, int $limit = 20, string $orderBy = 'created_at', string $order = 'DESC'): PaginationInterface
    {
        $queryBuilder = $this->repository->createQueryBuilder('u')
            ->orderBy("u.{$orderBy}", $order);

        $options = [
            'defaultSortFieldName' => "u.{$orderBy}",
            'defaultSortDirection' => $order,
            'sortFieldWhitelist' => ['u.username', 'u.email', 'u.created_at', 'u.updated_at'],
            'filterFieldWhitelist' => ['u.username', 'u.email']
        ];

        $logContext = [
            'entity_type' => 'User',
            'order_by' => $orderBy,
            'order_direction' => $order
        ];

        return $this->paginate(
            $queryBuilder,
            $page,
            $limit,
            $options,
            'user.paginated.retrieve',
            $logContext
        );
    }

    public function hashPassword(User $user): User
    {
        return $user->setPassword(
            $this->hasher->hashPassword($user, $user->getPassword())
        );
    }

    public function saveUser(User $user, bool $isCreation = false): bool
    {
        // Si c'est une création de compte et qu'aucun token n'est encore défini
        if ($isCreation && !$user->getVaultTokenSession()) {
            $user->setVaultTokenSession($this->generateStandardToken());
        }
        
        return $this->repository->save($user, true, $isCreation);
    }

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

    public function breadcrumb(array $items = []): Breadcrumb
    {
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

    public function requestEmailChange(User $user, string $newEmail): bool
    {
        try {
            $userRequest = $this->userRequestService->createUserRequest(UserRequestTypeEnum::AUTH_PROFILE_CHANGE_EMAIL);
            $userRequest->setContent(['new_email' => $newEmail]);
            $user->addUserRequest($userRequest);
            $this->saveUser($user, false);
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
     * @param User $user
     * @param string $newCode
     * @param string|null $currentCode
     * 
     * @return array
     */
    public function saveConfidentialCode(User $user, string $newCode, ?string $currentCode = null): array
    {
        if ($user->getPrivateSecret() !== null) {
            if ($currentCode === null) {
                return ['success' => false, 'error' => 'Le code actuel est requis.'];
            }

            // Vérification native avec l'ancien code
            if (!password_verify($currentCode, $user->getPrivateSecret())) {
                return ['success' => false, 'error' => 'Le code actuel est incorrect.'];
            }
        }

        if (mb_strlen($newCode) < 4) {
            return ['success' => false, 'error' => 'Le code doit contenir au moins 4 caractères.'];
        }

        // Hachage natif sécurisé pour le nouveau PIN
        $hashed = password_hash($newCode, PASSWORD_DEFAULT);
        $user->setPrivateSecret($hashed);

        $saved = $this->saveUser($user, false);

        if (!$saved) {
            return ['success' => false, 'error' => 'Une erreur est survenue lors de la sauvegarde.'];
        }

        $this->generateLog(
            LoggerLevelEnum::Info,
            ['message' => 'Code confidentiel mis à jour', 'user_id' => $user->getId()],
            ['action' => 'user.confidential_code.update.success']
        );

        return ['success' => true];
    }

    /**
     * @param string $code
     * 
     * @return bool
     */
    public function verifyConfidentialCode(string $code): bool
    {
        $user = $this->getCurrentUser();

        if ($user === null || $user->getPrivateSecret() === null) {
            return false;
        }

        // Utilisation de la fonction native PHP pour vérifier le hash du secret
        return password_verify($code, $user->getPrivateSecret());
    }

    /**
     * Vérifie si le token Base64 correspond à la session intime de l'utilisateur.
     * Cette méthode décode le Base64 et compare avec le token natif en base.
     */
    public function verifyVaultSession(string $base64Token): bool
    {
        $user = $this->getCurrentUser();
        
        if ($user === null || $user->getVaultTokenSession() === null) {
            return false;
        }

        // On décode le base64 (le true active le mode strict pour éviter la corruption)
        $decodedToken = base64_decode($base64Token, true);

        if ($decodedToken === false) {
            return false; 
        }

        // Comparaison stricte avec le token UUID v4 natif en base de données
        return $user->getVaultTokenSession() === $decodedToken;
    }

    public function refreshSession(User $user): void
    {
        $this->security->login($user, FormLoginAuthenticator::class, 'main');

        $this->generateLog(LoggerLevelEnum::Info, [
            'message' => 'Session rafraîchie automatiquement',
            'user_id' => $user->getId(),
        ], ['action' => 'user.session.refreshed']);
    }

    public function generateStandardToken(): string
    {
        return Uuid::v4()->toRfc4122();
    }
}