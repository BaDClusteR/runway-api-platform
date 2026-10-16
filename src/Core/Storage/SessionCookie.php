<?php

declare(strict_types=1);

namespace ApiPlatform\Core\Storage;

use Runway\Request\IRequest;
use Runway\Request\Parameters\DTO\CookieDTO;

class SessionCookie implements ISessionCookie {
    /**
     * Static: the container creates a new instance for every service using it (e.g. the endpoint sets the cookie,
     * the response controller sends it). A request is handled by its own PHP process.
     */
    protected static ?CookieDTO $change = null;

    public function __construct(
        protected IRequest $request,
        protected string   $cookieName = 'api_session'
    ) {}

    public function set(string $token, int $expires): void {
        static::$change = $this->createCookie($token, $expires);
    }

    public function delete(): void {
        static::$change = $this->createCookie('', 1);
    }

    public function takeChange(): ?CookieDTO {
        $change = static::$change;
        static::$change = null;

        return $change;
    }

    protected function createCookie(string $value, int $expires): CookieDTO {
        return new CookieDTO(
            name: $this->cookieName,
            value: $value,
            expires: $expires,
            // The whole site: e.g. the files protected by the web server (see "auth/check").
            path: '/',
            domain: '',
            isSecure: $this->request->getProtocol() === 'https',
            isHttponly: true,
            sameSite: 'Strict'
        );
    }
}
