<?php
namespace App\Controller\Auth;

use App\Form\Auth\LoginFormType;
use App\Service\UserRequest\UserRequestService;
use Fagathe\CorePhp\Logger\Logger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/auth', name: 'auth_')]
final class LoginController extends AbstractController
{
    private Logger $logger;

    public function __construct(
        private readonly Security $security,
        private readonly UserRequestService $userRequestService
    ) {
        // Créer une instance du Logger spécifique pour ce contrôleur
        $this->logger = new Logger('security/login/attempts-login', $security, true);
    }


    #[Route(path: '/login', name: 'login')]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        // Si l'utilisateur est déjà connecté, redirection
        if ($this->getUser()) {
            $this->logger->info([
                'message' => 'Tentative d\'accès à la page de connexion par un utilisateur déjà connecté',
                'user_id' => $this->getUser()->getUserIdentifier(),
                'redirect_to' => 'app_default_index'
            ]);
            return $this->redirectToRoute('app_default_index');
        }

        $this->logger->info([
            'message' => 'Accès à la page de connexion'
        ]);

        // Récupère l'erreur de connexion s'il y en a une
        $error = $authenticationUtils->getLastAuthenticationError();

        // Dernier nom d'utilisateur saisi par l'utilisateur
        $lastUsername = $authenticationUtils->getLastUsername();

        if ($error) {
            $this->logger->warning([
                'message' => 'Échec de tentative de connexion',
                'username' => $lastUsername,
                'error' => $error->getMessage(),
                'error_type' => get_class($error)
            ]);
        }

        if ($lastUsername) {
            $this->logger->info([
                'message' => 'Affichage du formulaire de connexion avec nom d\'utilisateur pré-rempli',
                'username' => $lastUsername
            ]);
        }

        // Crée le formulaire
        $form = $this->createForm(LoginFormType::class, [
            'username' => $lastUsername
        ]);

        return $this->render('auth/login/index.html.twig', [
            'form' => $form->createView(),
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'logout', methods: ['GET'])]
    public function logout(): void
    {
        // Log avant déconnexion
        if ($this->getUser()) {
            $this->logger->info([
                'message' => 'Déconnexion de l\'utilisateur',
                'user_id' => $this->getUser()->getUserIdentifier()
            ]);
        }

        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}