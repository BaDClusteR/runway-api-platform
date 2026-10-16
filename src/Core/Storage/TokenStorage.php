<?php

declare(strict_types=1);

namespace ApiPlatform\Core\Storage;

use Runway\Request\IRequest;

class TokenStorage implements ITokenStorage {
    /**
     * @param string|null $tokenHeader API_TOKEN_HEADER, "X-Token" by default.
     * @param string      $cookieName  The session cookie, see ISessionCookie.
     */
    public function __construct(
        protected IRequest $request,
        protected ?string  $tokenHeader = null,
        protected string   $cookieName = 'api_session'
    ) {}

    public function getToken(): string {
        return $this->getHeaderToken() ?? (string)$this->request->getCookie($this->cookieName);
    }

    public function isCookieToken(): bool {
        return $this->getHeaderToken() === null && $this->request->getCookie($this->cookieName) !== null;
    }

    protected function getHeaderToken(): ?string {
        $token = $this->request->getHeader($this->tokenHeader ?: 'X-Token');

        return ($token !== null && $token !== '') ? $token : null;
    }
}
