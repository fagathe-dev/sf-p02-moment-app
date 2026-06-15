<?php
namespace App\Controller\Auth;

use App\Entity\User;
use App\Enum\UserPreference\ThemePreferenceEnum;
use App\Form\Auth\Profile\ChangeEmailType;
use App\Form\Auth\Profile\ChangePasswordType;
use App\Form\Auth\Profile\ProfileInfoType;
use App\Security\Authenticator\FormLoginAuthenticator;
use App\Service\UserRequest\UserRequestService;
use App\Service\UserService;
use Fagathe\CorePhp\Uploader\FileUploadException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth/profile', name: 'auth_profile_')]
final class ProfileController extends AbstractController
{

    public function __construct(
        private readonly UserService $userService,
        private readonly Security $security,
    ) {
    }

    #[Route(path: '', name: 'index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser() ?? new User;
        $infoForm = $this->createForm(ProfileInfoType::class, $user);
        $infoForm->handleRequest($request);

        $themePreferences = ThemePreferenceEnum::choices();

        if ($infoForm->isSubmitted() && $infoForm->isValid()) {
            // Handle form submission, e.g., save the user data
            $boolUpdated = $this->userService->saveUser($user);
            if ($boolUpdated) {
                $this->addFlash('success', 'Votre profil a été mis à jour avec succès.');
            } else {
                $this->addFlash('error', 'Une erreur est survenue lors de la mise à jour de votre profil.');
            }

            return $this->redirectToRoute('auth_profile_index', ['t' => 'informations']);
        }

        $emailForm = $this->createForm(ChangeEmailType::class);
        $emailForm->handleRequest($request);

        if ($emailForm->isSubmitted() && $emailForm->isValid()) {
            $newEmail = $emailForm->get('email')->getData();
            /** @var User $user */
            $user = $this->getUser();

            $success = $this->userService->requestEmailChange($user, $newEmail);

            if ($success) {
                $this->addFlash('success', 'Un lien de confirmation a été envoyé à votre nouvelle adresse e-mail (' . $newEmail . '). Veuillez cliquer dessus pour valider le changement.');
            } else {
                $this->addFlash('error', 'Une erreur est survenue lors de la demande de changement d\'e-mail.');
            }

            return $this->redirectToRoute('auth_profile_index', ['t' => 'settings']);
        }

        $passwordForm = $this->createForm(ChangePasswordType::class);
        $passwordForm->handleRequest($request);

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            /** @var User $user */
            $user = $this->getUser();

            // 1. Récupération du nouveau mot de passe depuis le formulaire
            $newPassword = $passwordForm->get('new_password')->getData();

            // 2. On l'affecte à l'utilisateur
            $user->setPassword($newPassword);

            // 3. Hashage et sauvegarde via le UserService
            $user = $this->userService->hashPassword($user);
            $this->userService->saveUser($user, false);

            // 🔥 4. RECONNEXION SANS COUTURE 🔥
            $this->userService->refreshSession($user);

            $this->addFlash('success', 'Votre mot de passe a été mis à jour avec succès.');

            return $this->redirectToRoute('auth_profile_index', ['t' => 'settings']);
        }

        return $this->render('auth/profile/index.html.twig', compact('infoForm', 'emailForm', 'passwordForm', 'user', 'themePreferences'));
    }

    #[Route(path: '/update', name: 'update', methods: ['POST'])]
    public function update(Request $request): JsonResponse
    {
        return $this->json([
            'message' => 'Profile mis à jour avec succès',
        ]);
    }

    #[Route(path: '/upload/avatar', name: 'upload_avatar', methods: ['POST'])]
    public function uploadAvatar(Request $request): JsonResponse
    {
        $file = $request->files->get('avatar');

        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => 'Aucun fichier reçu.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->userService->updateAvatar($this->getUser(), $file);

            return $this->json([
                'message' => 'Avatar mis à jour avec succès.',
                'url' => $result->relativePath,
            ]);
        } catch (FileUploadException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route(path: '/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // 1. Vérification de sécurité (CSRF)
        $csrfToken = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('delete_account' . $user->getId(), $csrfToken)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('auth_profile_index', ['t' => 'settings']);
        }

        // 2. Suppression de l'utilisateur via le service (qui gère aussi l'avatar)
        $isDeleted = $this->userService->deleteUser($user->getId());

        if ($isDeleted) {
            // 3. Déconnexion de l'utilisateur
            // Le paramètre "false" empêche de jeter une exception si on n'est pas derrière un pare-feu
            $this->security->logout(false);

            // Invalidation de la session pour nettoyer les traces
            $request->getSession()->invalidate();

            // Redirection vers l'accueil ou la page de connexion
            return $this->redirectToRoute('auth_login'); // Ou 'app_home' selon tes routes
        }

        // Cas d'erreur (rare si l'utilisateur est bien connecté)
        $this->addFlash('error', 'Une erreur est survenue lors de la suppression du compte.');
        return $this->redirectToRoute('auth_profile_index', ['t' => 'settings']);
    }

    // 🔥 LA NOUVELLE ROUTE MAGIQUE 🔥
    #[Route(path: '/confirm-email/{token}', name: 'confirm_email', methods: ['GET'])]
    public function confirmEmailChange(string $token, UserRequestService $userRequestService): Response
    {
        $user = $userRequestService->confirmEmailChange($token);

        if ($user) {
            // Reconnexion automatique et transparente !
            $this->security->login($user, FormLoginAuthenticator::class, 'main');

            $this->addFlash('success', 'Votre adresse e-mail a été mise à jour avec succès.');
            return $this->redirectToRoute('auth_profile_index', ['t' => 'settings']);
        }

        $this->addFlash('error', 'Ce lien de confirmation est invalide ou a expiré.');
        return $this->redirectToRoute('auth_profile_index', ['t' => 'settings']);
    }

}