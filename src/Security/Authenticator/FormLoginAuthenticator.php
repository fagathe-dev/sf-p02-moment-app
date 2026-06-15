<?php

namespace App\Security\Authenticator;

use App\Security\Enum\RoleEnum;
use App\Security\RoleChecker;
use App\Service\UserService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class FormLoginAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'auth_login';
    public const DEFAULT_ROUTE = 'app_default_index';
    public const DASHBOARD_ROUTE = 'dashboard_index';
    public const API_TOKEN_COOKIE_NAME = '__ffr_v.aoth.tkn';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private UserService $userService,
        private RoleChecker $roleChecker,
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $login = trim($request->getPayload()->getString('username', ''));

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $login);

        return new Passport(
            new UserBadge($login),
            new PasswordCredentials(trim($request->getPayload()->getString('password', ''))),
            [
                new CsrfTokenBadge('authenticate', trim($request->getPayload()->getString('_token', ''))),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {

        return new RedirectResponse($this->urlGenerator->generate(self::DEFAULT_ROUTE));

        /** @var \App\Entity\User $user */
        $user = $token->getUser();
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        if ($this->roleChecker->check(RoleEnum::ROLE_EDITOR, $user)) {
            return new RedirectResponse($this->urlGenerator->generate(self::DASHBOARD_ROUTE));
        }

        return new RedirectResponse($this->urlGenerator->generate(self::DEFAULT_ROUTE));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
