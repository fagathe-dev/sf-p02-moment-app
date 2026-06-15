<?php
namespace App\Controller\Auth;

use App\Entity\User;
use App\Form\Auth\RegistrationFormType;
use App\Service\UserService;
use App\Service\UserRequest\UserRequestService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth/registration', name: 'auth_registration_')]
final class RegistrationController extends AbstractController
{

    public function __construct(
        private readonly UserService $userService,
        private readonly UserRequestService $userRequestService
    ) {
    }

    #[Route(path: '', name: 'index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            return $this->redirectToRoute('auth_login');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $isSaved = $this->userService->register($user);

            if ($isSaved) {
                return $this->redirectToRoute('auth_login');
            }
        }


        return $this->render('auth/registration/index.html.twig', compact('form', 'user'));
    }

    #[Route(path: '/confirm-account/{token}', name: 'confirm_account', methods: ['GET'])]
    public function confirmAccount(string $token): Response
    {
        $isConfirmed = $this->userRequestService->confirmAccount($token);

        if ($isConfirmed) {
            return $this->redirectToRoute('auth_login');
        }

        // En cas d'échec, rediriger vers la page d'inscription avec le message d'erreur
        return $this->redirectToRoute('auth_registration_index');
    }
}