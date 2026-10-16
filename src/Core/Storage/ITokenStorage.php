<?php

declare(strict_types=1);

namespace ApiPlatform\Core\Storage;

/**
 * The session token of the request: from the header (e.g. a script) or from the cookie (a browser).
 */
interface ITokenStorage {
    /**
     * @return string Empty if there is no token.
     */
    public function getToken(): string;

    /**
     * Whether the token is from the cookie: the browser sends it with any request to the site, so an unsafe request
     * from another site is checked (see ApiController).
     */
    public function isCookieToken(): bool;
}
