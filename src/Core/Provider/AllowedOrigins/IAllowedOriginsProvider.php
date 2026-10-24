<?php

namespace ApiPlatform\Core\Provider\AllowedOrigins;

/**
 * The sites allowed to call the API from a browser (API_ALLOW_ORIGIN): they get the CORS headers, and their pages can
 * make changes (see ApiController::checkOrigin()).
 */
interface IAllowedOriginsProvider {
    /**
     * @return string[] E.g. ["https://app.example.com"], or ["*"] for any site.
     */
    public function getAllowedOrigins(): array;

    /**
     * @param string $origin The "Origin" header, e.g. "https://app.example.com".
     */
    public function isAllowed(string $origin): bool;

    /**
     * Whether any site is allowed ("*").
     */
    public function isAnyAllowed(): bool;
}
