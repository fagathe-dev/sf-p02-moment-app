<?php

namespace App\Controller\Auth;

use App\Entity\User;
use App\Form\Auth\ForgotPassword\ResetPasswordType;
use App\Form\Auth\ForgotPassword\SendTokenFormType;
use App\Service\UserRequest\UserRequestService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @deprecated Utilisez ForgotPasswordController à la place (/auth/forgot-password).
 * Ce contrôleur est conservé pour compatibilité avec les anciens liens.
 * Le flow principal passe désormais par ForgotPasswordController.
 */
#[Route('/auth/reset-password', name: 'auth_reset_password_')]
final class ResetPasswordController extends AbstractController
{

    public function __construct(
        private readonly UserRequestService $userRequestService
    ) {
    }

    /**
     * Étape 1 : Formulaire de saisie de l'email pour recevoir le token de réinitialisation.
     * TODO: Cette étape devrais être déplacée dans la page de gestion de profil pour les utilisateurs connectés (ex: /auth/profile/security) et renommée "Changer mon mot de passe" (sans mention de token).
     * Dans le process cliquer sur le bouton "Changer mon mot de passe" depuis `/auth/profile/security` ou `/auth/account/settings` envoi d'un email avec un token de réinitialisation (même process que "Mot de passe oublié") et redirection vers le formulaire de saisie du nouveau mot de passe (actuellement /auth/reset-password/{token}).
     * 
     * Affiche le formulaire d'email et déclenche l'envoi du token.
     * Le message de succès est identique que l'email existe ou non
     * (protection anti-énumération).
     */
    #[Route(path: '/request', name: 'request', methods: ['GET', 'POST'])]
    public function sendToken(Request $request): Response
    {
        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute('app_default_index');
        }

        $form = $this->createForm(SendTokenFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();

            // Le service gère la protection anti-énumération :
            // même flash "success" que l'email existe ou non
            $this->userRequestService->sendPasswordResetEmail($email);

            return $this->redirectToRoute('auth_reset_password_request');
        }

        return $this->render('auth/reset-password/request.html.twig', compact('form'));
    }

    /**
     * Étape 2 : Formulaire de saisie du nouveau mot de passe.
     * 
     * Vérifie la validité du token et permet la saisie
     * du nouveau mot de passe avec confirmation.
     */
    #[Route(path: '/{token}', name: 'action', methods: ['GET', 'POST'])]
    public function resetPasswordForm(string $token, Request $request): Response
    {
        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute('app_default_index');
        }

        // Vérifier que le token existe avant d'afficher le formulaire
        $userRequest = $this->userRequestService->findByToken($token);
        if ($userRequest === null) {
            $this->addFlash('danger', 'Ce lien de réinitialisation est invalide ou a expiré.');
            return $this->redirectToRoute('auth_reset_password_request');
        }

        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('password')->getData();

            if ($this->userRequestService->resetPassword($token, $newPassword)) {
                return $this->redirectToRoute('auth_login');
            }
        }

        return $this->render('auth/reset-password/action.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}