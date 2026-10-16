<?php

declare(strict_types=1);

namespace ApiPlatform\Controller;

/**
 * For the decorators of the Runway controllers: the API requests get JSON responses, the others go to the decorated
 * controller. Requires the $pathPrefix property (the API path prefix, e.g. "/api").
 */
trait ApiPathTrait {
    protected function isApiPath(string $path): bool {
        $prefix = rtrim($this->pathPrefix, '/');

        return $path === $prefix || str_starts_with($path, "$prefix/");
    }
}
