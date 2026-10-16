<?php

declare(strict_types=1);

namespace ApiPlatform\Core\Storage;

use Runway\Request\Parameters\DTO\CookieDTO;

/**
 * The session cookie set or deleted by the response (e.g. of a login): HttpOnly (the scripts of the page cannot read
 * it), SameSite=Strict (the browser does not send it with the requests of other sites), Secure over HTTPS.
 */
interface ISessionCookie {
    /**
     * @param int $expires Unix timestamp.
     */
    public function set(string $token, int $expires): void;

    public function delete(): void;

    /**
     * The cookie to send with the response, if it's changed: it's sent once.
     */
    public function takeChange(): ?CookieDTO;
}
