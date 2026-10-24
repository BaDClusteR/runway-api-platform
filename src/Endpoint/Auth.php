<?php

declare(strict_types=1);

namespace ApiPlatform\Endpoint;

use ApiPlatform\Attribute as Api;
use ApiPlatform\Attribute\Assert;
use ApiPlatform\Attribute\Docs;
use ApiPlatform\Core\Singleton\IAuth;
use ApiPlatform\Core\Storage\ISessionCookie;
use ApiPlatform\Core\Storage\ITokenStorage;
use ApiPlatform\Endpoint\DTO\Auth\DoneDTO;
use ApiPlatform\Endpoint\DTO\Auth\LoginDTO;
use ApiPlatform\Endpoint\DTO\Auth\StatusDTO;
use ApiPlatform\Exception\ApiException;
use ApiPlatform\Exception\AuthFailedException;
use ApiPlatform\Exception\AuthorizationRequiredException;
use ApiPlatform\Exception\TooManyRequestsException;
use Runway\Request\IRequest;

/**
 * The login of the only user (see IAuth): a browser gets the session in the cookie, a script - the token for the
 * token header.
 */
#[Docs\Group('Authorization')]
#[Api\Prefix('auth')]
class Auth {
    public function __construct(
        protected IAuth          $auth,
        protected ITokenStorage  $tokenStorage,
        protected ISessionCookie $sessionCookie,
        protected IRequest       $request
    ) {}

    #[Api\Get('status', public: true)]
    #[Docs\Endpoint('Whether the client is logged in')]
    public function status(): StatusDTO {
        return new StatusDTO(
            isEnabled: $this->auth->isEnabled(),
            isAuthenticated: !$this->auth->isEnabled() || $this->auth->isAuthenticated($this->tokenStorage->getToken())
        );
    }

    /**
     * For the web server: e.g. nginx "auth_request" serves the files only to the logged-in clients.
     *
     * @throws AuthorizationRequiredException
     */
    #[Api\Get('check', public: true)]
    #[Docs\Endpoint('204 if the client is logged in (or the authorization is disabled), 401 otherwise')]
    #[Docs\Throws(code: 401, description: 'Not logged in')]
    public function check(): DoneDTO {
        if ($this->auth->isEnabled() && !$this->auth->isAuthenticated($this->tokenStorage->getToken())) {
            throw new AuthorizationRequiredException();
        }

        return new DoneDTO();
    }

    /**
     * @throws ApiException
     * @throws AuthFailedException
     * @throws TooManyRequestsException
     */
    #[Api\Post('login', public: true)]
    #[Docs\Endpoint('Log in')]
    #[Docs\Throws(code: 401, description: 'Invalid credentials')]
    #[Docs\Throws(code: 404, description: 'The authorization is disabled')]
    #[Docs\Throws(code: 429, description: 'Too many failed logins: try again later')]
    public function login(
        #[Api\Body]
        #[Assert\NotEmpty]
        #[Docs\Argument(example: 'myLogin')]
        string $login,
        #[Api\Body]
        #[Assert\NotEmpty]
        #[Docs\Argument(format: 'password', example: 'myPassword')]
        string $password,
        #[Api\Body]
        #[Docs\Argument(description: 'Set the session cookie (a browser), otherwise the token is returned (a script)')]
        bool   $useCookie = true
    ): LoginDTO {
        $this->checkEnabled();

        $session = $this->auth->logIn($login, $password, $this->request->getIpAddress(), $this->request->getUserAgent())
            ?? throw new AuthFailedException();

        // A new login ends the current session of the client (a failed one does not).
        $this->auth->logOut($this->tokenStorage->getToken());

        if ($useCookie) {
            $this->sessionCookie->set($session->token, $session->validUntil);
        }

        return new LoginDTO($useCookie ? null : $session->token, date(DATE_ATOM, $session->validUntil));
    }

    #[Api\Post('logout', public: true)]
    #[Docs\Endpoint('Log out: the session ends')]
    public function logout(): DoneDTO {
        $this->auth->logOut($this->tokenStorage->getToken());

        if ($this->tokenStorage->isCookieToken()) {
            $this->sessionCookie->delete();
        }

        return new DoneDTO();
    }

    /**
     * Without the authorization (API_AUTH_ENABLED=false) there are no credentials nor sessions.
     *
     * @throws ApiException
     */
    protected function checkEnabled(): void {
        if (!$this->auth->isEnabled()) {
            throw new ApiException(['The authorization is disabled.'], 404);
        }
    }
}
