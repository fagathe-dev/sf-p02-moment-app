<?php
namespace App\Controller\App;

use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class VaultController extends AbstractController
{
    public function __construct(private readonly UserService $userService)
    {
    }

    #[Route('/api/vault/check', methods: ['POST'])]
    public function check(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $pinCode = $payload['pin'] ?? '';

        // Vérifie si le code PIN correspond à l'utilisateur connecté
        if (!$this->userService->verifyConfidentialCode($pinCode)) {
            return new JsonResponse(['error' => 'Code confidentiel invalide'], 403);
        }

        // Récupère l'utilisateur courant via le service
        $user = $this->userService->getCurrentUser();
        
        if (!$user || !$user->getVaultTokenSession()) {
            return new JsonResponse(['error' => 'Erreur de session'], 500);
        }

        // On encode le token natif en Base64 pour le front-end
        $base64Token = base64_encode($user->getVaultTokenSession());

        return new JsonResponse(['token' => $base64Token]);
    }

    #[Route('/api/vault/verify', methods: ['GET'])]
    public function verify(Request $request): JsonResponse
    {
        // 1. Récupération du header Authorization (ex: "Bearer MTIzZT...")
        $authHeader = $request->headers->get('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return new JsonResponse(['error' => 'Token manquant ou mal formaté'], 401);
        }

        // 2. Extraction du token en base64
        $base64Token = substr($authHeader, 7);

        // 3. Vérification via le UserService
        if (!$this->userService->verifyVaultSession($base64Token)) {
            return new JsonResponse(['error' => 'Session invalide ou expirée'], 403);
        }

        return new JsonResponse(['success' => true]);
    }
}