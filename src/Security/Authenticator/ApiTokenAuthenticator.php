<?php

declare(strict_types=1);

namespace App\Security\Authenticator;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public const AUTH_HEADER = 'X-AUTH-TOKEN';

    public function __construct(private readonly UserRepository $userRepository) {}

    /**
     * Décide si cet authentificateur doit être utilisé pour la requête.
     * Simplification : On vérifie simplement si le chemin commence par /api
     * car cela inclut implicitement /api/admin.
     */
    public function supports(Request $request): ?bool
    {
        // Le firewall filtre déjà sur ^/api, mais cette vérification renforce la sécurité
        // et s'assure que le token est présent.
        return $request->headers->has(self::AUTH_HEADER) && str_starts_with($request->getPathInfo(), '/api');
    }

    /**
     * Récupère les identifiants pour créer le Passport.
     */
    public function authenticate(Request $request): Passport
    {
        $apiToken = $request->headers->get(self::AUTH_HEADER);

        if (null === $apiToken) {
            throw new CustomUserMessageAuthenticationException('No API token provided in ' . self::AUTH_HEADER . ' header.');
        }

        // On utilise l'API Token comme identifiant pour la recherche de l'utilisateur
        return new SelfValidatingPassport(
            new UserBadge(
                $apiToken,
                // Fonction de chargement de l'utilisateur qui cherche par apiToken
                function ($userIdentifier) {
                    $user = $this->userRepository->findOneBy(['apiToken' => $userIdentifier]);
                    
                    if (!$user) {
                         throw new CustomUserMessageAuthenticationException('Invalid API token.');
                    }
                    return $user;
                }
            )
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Succès : La requête continue (null = pas de réponse spécifique, on laisse passer au contrôleur)
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // Échec : Retourne un JSON 401 Unauthorized
        $data = [
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }
}