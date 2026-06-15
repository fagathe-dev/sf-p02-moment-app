<?php

namespace App\Service\UserRequest;

use App\Emails\Auth\ResetPasswordEmail;
use App\Entity\UserRequest;
use App\Repository\UserRepository;
use App\Repository\UserRequestRepository;
use App\Security\Authenticator\FormLoginAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Fagathe\CorePhp\Enum\LoggerLevelEnum;
use Fagathe\CorePhp\Generator\TokenGenerator;
use Fagathe\CorePhp\Trait\DatetimeTrait;
use Fagathe\CorePhp\Trait\LoggerTrait;
use Fagathe\CorePhp\Trait\SessionFlashTrait;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

/**
 * Service de gestion des demandes utilisateur (UserRequest).
 * 
 * Gère la validation et le traitement des demandes comme
 * la confirmation de compte, la réinitialisation de mot de passe, etc.
 * 
 * @author fagathe-dev <https:/>/github.com/fagathe-dev/>
 */
final class UserRequestService
{
    use LoggerTrait, DatetimeTrait, SessionFlashTrait;

    public function __construct(
        private readonly UserRequestRepository $repository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly SerializerInterface $serializer,
        protected readonly PaginatorInterface $paginator,
        private readonly TokenGenerator $tokenGenerator,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ResetPasswordEmail $resetPasswordEmail,

    ) {
    }

    /**
     * Valide une demande utilisateur.
     * 
     * Vérifie que :
     * - La demande existe en base de données
     * - Le type est valide (correspond à un UserRequestTypeEnum)
     * - La demande n'a pas déjà été utilisée
     * - La date d'expiration n'est pas dépassée (si définie)
     * 
     * @param UserRequest $userRequest La demande à valider
     * 
     * @return UserRequestValidationResult Résultat de la validation
     */
    public function validate(UserRequest $userRequest, UserRequestTypeEnum $type): UserRequestValidationResult
    {
        // Vérifier que la demande existe en BDD (re-fetch pour s'assurer qu'elle est gérée)
        $existingRequest = $this->repository->find($userRequest->getId());
        if ($existingRequest === null) {
            $this->generateLog(
                LoggerLevelEnum::Warning,
                [
                    'message' => 'Tentative de validation d\'une demande inexistante',
                    'token' => $userRequest->getToken()
                ],
                ['action' => 'user_request.validate.not_found']
            );
            return new UserRequestValidationResult(false, 'La demande n\'existe pas.');
        }

        // Vérifier que le type est valide
        $isCorrectType = $userRequest->getType() === $type;

        if ($isCorrectType === false) {
            $this->generateLog(
                LoggerLevelEnum::Warning,
                [
                    'message' => 'Type de demande invalide',
                    'type' => $userRequest->getType()?->value,
                    'token' => $userRequest->getToken()
                ],
                ['action' => 'user_request.validate.invalid_type']
            );
            return new UserRequestValidationResult(false, 'Le type de demande est invalide.');
        }

        // Vérifier que la demande n'a pas déjà été utilisée
        if ($userRequest->isUsed() === true) {
            $this->generateLog(
                LoggerLevelEnum::Warning,
                [
                    'message' => 'Tentative d\'utilisation d\'une demande déjà utilisée',
                    'token' => $userRequest->getToken(),
                    'type' => $userRequest->getType()
                ],
                ['action' => 'user_request.validate.already_used']
            );
            return new UserRequestValidationResult(false, 'Cette demande a déjà été utilisée.');
        }

        // Vérifier la date d'expiration si elle est définie
        $expiresAt = $userRequest->getExpiresAt();
        if ($expiresAt !== null && $expiresAt < $this->now()) {
            $this->generateLog(
                LoggerLevelEnum::Warning,
                [
                    'message' => 'Tentative d\'utilisation d\'une demande expirée',
                    'token' => $userRequest->getToken(),
                    'type' => $userRequest->getType(),
                    'expires_at' => $expiresAt->format('Y-m-d H:i:s')
                ],
                ['action' => 'user_request.validate.expired']
            );
            return new UserRequestValidationResult(false, 'Cette demande a expiré.');
        }

        // Vérifier que l'utilisateur associé existe
        if ($userRequest->getUser() === null) {
            $this->generateLog(
                LoggerLevelEnum::Warning,
                [
                    'message' => 'Demande sans utilisateur associé',
                    'token' => $userRequest->getToken(),
                    'type' => $userRequest->getType()
                ],
                ['action' => 'user_request.validate.no_user']
            );
            return new UserRequestValidationResult(false, 'Aucun utilisateur n\'est associé à cette demande.');
        }

        return new UserRequestValidationResult(true, 'La demande est valide.', $type);
    }

    /**
     * Traite la confirmation de compte utilisateur.
     * 
     * - Valide la demande
     * - Marque la demande comme utilisée
     * - Active le compte utilisateur (isVerified = true)
     * 
     * @param string $token Le token de la demande de confirmation
     * 
     * @return bool True si la confirmation a réussi, false sinon
     */
    public function confirmAccount(string $token): bool
    {
        $userRequest = $this->repository->findOneBy(compact('token'));

        if ($userRequest === null) {
            $this->generateLog(
                LoggerLevelEnum::Warning,
                [
                    'message' => 'Tentative de confirmation de compte avec un token invalide',
                    'token' => $token
                ],
                ['action' => 'user_request.confirm_account.not_found']
            );
            $this->addFlash('danger', 'Le token de confirmation est invalide.');
            return false;
        }

        // Valider la demande
        $validationResult = $this->validate($userRequest, UserRequestTypeEnum::AUTH_ACCOUNT_VERIFICATION);

        if (!$validationResult->isValid()) {
            $this->addFlash('danger', $validationResult->getMessage());
            return false;
        }

        // Vérifier que c'est bien une demande de confirmation de compte
        if ($validationResult->getType() !== UserRequestTypeEnum::AUTH_ACCOUNT_VERIFICATION) {
            $this->generateLog(
                LoggerLevelEnum::Warning,
                [
                    'message' => 'Type de demande incorrect pour la confirmation de compte',
                    'expected' => UserRequestTypeEnum::AUTH_ACCOUNT_VERIFICATION->value,
                    'actual' => $userRequest->getType()
                ],
                ['action' => 'user_request.confirm_account.wrong_type']
            );
            $this->addFlash('danger', 'Le type de demande est incorrect.');
            return false;
        }

        try {
            $now = $this->now();
            $user = $userRequest->getUser();

            // Mettre à jour la demande (UserRequest)
            $userRequest
                ->setIsUsed(true)
                ->setUpdatedAt($now);

            // Mettre à jour l'utilisateur (User)
            $user
                ->setIsVerified(true)
                ->setUpdatedAt($now);

            // Connexion automatique de l'utilisateur après confirmation
            $this->security->login($user, FormLoginAuthenticator::class, 'main'); // 'main' correspond au nom de ton firewall

            // Persister les modifications
            $this->entityManager->flush();

            $this->generateLog(
                LoggerLevelEnum::Info,
                [
                    'message' => 'Compte utilisateur confirmé avec succès',
                    'user_id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail()
                ],
                ['action' => 'user_request.confirm_account.success']
            );

            $this->addFlash('success', 'Votre compte a été confirmé avec succès ! Vous pouvez maintenant vous connecter.');
            return true;

        } catch (Throwable $e) {
            $this->generateLog(
                LoggerLevelEnum::Critical,
                [
                    'message' => 'Erreur lors de la confirmation du compte',
                    'token' => $userRequest->getToken(),
                    'error' => $e->getMessage()
                ],
                ['action' => 'user_request.confirm_account.error']
            );
            $this->addFlash('danger', 'Une erreur est survenue lors de la confirmation de votre compte.');
            return false;
        }
    }

    public function confirmEmailChange(string $token): ?\App\Entity\User
    {
        $userRequest = $this->repository->findOneBy(compact('token'));

        if ($userRequest === null) {
            return null;
        }

        $validationResult = $this->validate($userRequest, UserRequestTypeEnum::AUTH_PROFILE_CHANGE_EMAIL);

        if (!$validationResult->isValid()) {
            $this->addFlash('danger', $validationResult->getMessage());
            return null;
        }

        try {
            $now = $this->now();
            $user = $userRequest->getUser();
            $content = $userRequest->getContent();

            // Vérification de la présence du JSON
            if (!isset($content['new_email'])) {
                return null;
            }

            // Mettre à jour l'utilisateur
            $user->setEmail($content['new_email'])
                ->setUpdatedAt($now);

            // Mettre à jour la requête
            $userRequest->setIsUsed(true)
                ->setUpdatedAt($now);

            $this->entityManager->flush();

            $this->generateLog(LoggerLevelEnum::Info, [
                'message' => 'E-mail modifié avec succès via token',
                'user_id' => $user->getId(),
                'new_email' => $content['new_email']
            ], ['action' => 'user_request.change_email.success']);

            return $user;

        } catch (\Throwable $e) {
            $this->generateLog(LoggerLevelEnum::Critical, [
                'message' => 'Erreur lors de la validation du nouvel e-mail',
                'token' => $token,
                'error' => $e->getMessage()
            ], ['action' => 'user_request.change_email.error']);

            return null;
        }
    }

    /**
     * Trouve une demande par son token.
     * 
     * @param string $token Le token de la demande
     * 
     * @return UserRequest|null La demande trouvée ou null
     */
    public function findByToken(string $token): ?UserRequest
    {
        return $this->repository->findOneBy(['token' => $token]);
    }

    public function createUserRequest(UserRequestTypeEnum $requestTypeEnum): UserRequest
    {
        $userRequest = new UserRequest();

        $userRequest->setToken($this->tokenGenerator->generate(32))
            ->setType($requestTypeEnum)
            ->setIsUsed(false)
            ->setCreatedAt($this->now())
            ->setExpiresAt((clone $this->now())->modify('+24 hours'))
        ;

        return $userRequest;
    }

    /**
     * Réinitialise le mot de passe d'un utilisateur.
     * 
     * Vérifie la validité du token de réinitialisation et met à jour
     * le mot de passe de l'utilisateur en base de données.
     * 
     * @param string $token       Le token de réinitialisation
     * @param string $newPassword Le nouveau mot de passe en clair
     * 
     * @return bool True si la réinitialisation a réussi, false sinon
     */
    public function resetPassword(string $token, string $newPassword): bool
    {
        $userRequest = $this->repository->findOneBy(compact('token'));

        if ($userRequest === null) {
            $this->generateLog(
                LoggerLevelEnum::Warning,
                [
                    'message' => 'Tentative de réinitialisation de mot de passe avec un token invalide',
                    'token' => $token
                ],
                ['action' => 'user_request.reset_password.not_found']
            );
            $this->addFlash('danger', 'Le lien que vous avez utilisé est invalide.');
            return false;
        }

        // Valider la demande
        $validationResult = $this->validate($userRequest, UserRequestTypeEnum::AUTH_PASSWORD_RESET);

        if (!$validationResult->isValid()) {
            $this->addFlash('danger', $validationResult->getMessage());
            return false;
        }

        // Vérifier que c'est bien une demande de réinitialisation de mot de passe
        if ($validationResult->getType() !== UserRequestTypeEnum::AUTH_PASSWORD_RESET) {
            $this->generateLog(
                LoggerLevelEnum::Warning,
                [
                    'message' => 'Type de demande incorrect pour la réinitialisation de mot de passe',
                    'expected' => UserRequestTypeEnum::AUTH_PASSWORD_RESET->value,
                    'actual' => $userRequest->getType()
                ],
                ['action' => 'user_request.reset_password.wrong_type']
            );
            $this->addFlash('danger', 'Le type de demande est incorrect.');
            return false;
        }

        try {
            $now = $this->now();
            $user = $userRequest->getUser();

            // Hasher le nouveau mot de passe
            $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);

            // Mettre à jour la demande (UserRequest)
            $userRequest
                ->setIsUsed(true)
                ->setUpdatedAt($now);

            // Mettre à jour le mot de passe de l'utilisateur
            $user
                ->setPassword($hashedPassword)
                ->setUpdatedAt($now);

            // Persister les modifications
            $this->entityManager->flush();

            $this->generateLog(
                LoggerLevelEnum::Info,
                [
                    'message' => 'Mot de passe réinitialisé avec succès',
                    'user_id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail()
                ],
                ['action' => 'user_request.reset_password.success']
            );

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès ! Vous pouvez maintenant vous connecter.');
            return true;

        } catch (Throwable $e) {
            $this->generateLog(
                LoggerLevelEnum::Critical,
                [
                    'message' => 'Erreur lors de la réinitialisation du mot de passe',
                    'token' => $userRequest->getToken(),
                    'error' => $e->getMessage()
                ],
                ['action' => 'user_request.reset_password.error']
            );
            $this->addFlash('danger', 'Une erreur est survenue lors de la réinitialisation de votre mot de passe.');
            return false;
        }
    }

    /**
     * Envoie l'email de réinitialisation de mot de passe à l'utilisateur.
     * @param string $email
     * 
     * @return void
     */
    public function sendPasswordResetEmail(string $email): void
    {
        try {
            $user = $this->userRepository->findOneBy(['email' => $email]);

            if (null === $user) {
                $this->generateLog(
                    LoggerLevelEnum::Warning,
                    [
                        'message' => 'Tentative d\'envoi d\'email de réinitialisation de mot de passe pour un email non trouvé',
                        'email' => $email
                    ],
                    ['action' => 'user_request.send_password_reset_email.email_not_found']
                );
                // Ne pas indiquer à l'utilisateur que l'email n'existe pas pour des raisons de sécurité
                $this->addFlash('success', 'Un email vous a été envoyé !');
                return;
            }

            $userRequest = $this->createUserRequest(UserRequestTypeEnum::AUTH_PASSWORD_RESET);
            $user->addUserRequest($userRequest);

            $emailSent = $this->userRepository->save(user: $user, isCreation: false);

            if ($emailSent) {
                $this->resetPasswordEmail->send($userRequest);
            }

            // Message générique identique (anti-énumération)
            $this->addFlash('success', 'Un email vous a été envoyé !');

        } catch (Throwable $th) {
            $this->generateLog(
                LoggerLevelEnum::Critical,
                [
                    'message' => 'Erreur lors de l\'envoi de l\'email de réinitialisation de mot de passe',
                    'email' => $email,
                    'error' => $th->getMessage()
                ],
                ['action' => 'user_request.send_password_reset_email.error']
            );
        }


    }

}
