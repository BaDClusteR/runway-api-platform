<?php

declare(strict_types=1);

namespace ApiPlatform\Core\Storage;

use Runway\Request\IRequest;

class TokenStorage implements ITokenStorage {
    public function __construct(
        protected IRequest $request,
        protected string   $tokenHeader,
    ) {}

    public function getToken(): string {
        return (string)$this->request->getHeader(
            $this->tokenHeader
        );
    }
}