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
 * Contrôleur unifié pour la gestion du mot de passe oublié.
 * 
 * Regroupe :
 * - La demande de réinitialisation (envoi du token par email)
 * - La réinitialisation effective (saisie du nouveau mot de passe)
 * 
 * Protection anti-énumération : le même message de succès est affiché
 * que l'adresse email existe ou non en base de données.
 */
#[Route('/auth/forgot-password', name: 'auth_forgot_password_')]
final class ForgotPasswordController extends AbstractController
{

    public function __construct(
        private readonly UserRequestService $userRequestService
    ) {
    }

    /**
     * Étape 1 : Formulaire de demande de réinitialisation.
     * 
     * Affiche le formulaire d'email et déclenche l'envoi du token.
     * Le message de succès est identique que l'email existe ou non
     * (protection anti-énumération).
     */
    #[Route(path: '', name: 'request', methods: ['GET', 'POST'])]
    public function forgotPasswordRequest(Request $request): Response
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

            return $this->redirectToRoute('auth_forgot_password_request');
        }

        return $this->render('auth/forgot-password/request.html.twig', compact('form'));
    }

    /**
     * Étape 2 : Formulaire de saisie du nouveau mot de passe.
     * 
     * Vérifie la validité du token et permet la saisie
     * du nouveau mot de passe avec confirmation.
     */
    #[Route(path: '/action/{token}', name: 'action', methods: ['GET', 'POST'])]
    public function forgotPasswordAction(string $token, Request $request): Response
    {
        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute('app_default_index');
        }

        // Vérifier que le token existe avant d'afficher le formulaire
        $userRequest = $this->userRequestService->findByToken($token);
        if ($userRequest === null) {
            $this->addFlash('danger', 'Ce lien de réinitialisation est invalide ou a expiré.');
            return $this->redirectToRoute('auth_forgot_password_request');
        }

        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('password')->getData();

            if ($this->userRequestService->resetPassword($token, $newPassword)) {
                return $this->redirectToRoute('auth_login');
            }
        }

        return $this->render('auth/forgot-password/action.html.twig', compact('form'));
    }
}
