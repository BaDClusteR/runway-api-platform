<?php

declare(strict_types=1);

namespace ApiPlatform\Core\Provider\AllowedOrigins;

use Runway\Env\Provider\IEnvVariablesProvider;

class AllowedOriginsProvider implements IAllowedOriginsProvider {
    public function __construct(
        protected IEnvVariablesProvider $envVariablesProvider
    ) {}

    /**
     * The origins of API_ALLOW_ORIGIN (comma-separated), without the trailing slashes.
     */
    public function getAllowedOrigins(): array {
        $value = $this->envVariablesProvider->getEnvVariable('API_ALLOW_ORIGIN');

        return array_values(array_filter(
            array_map(
                static fn(string $origin): string => rtrim(trim($origin), '/'),
                explode(',', is_string($value) ? $value : '')
            ),
            static fn(string $origin): bool => $origin !== ''
        ));
    }

    public function isAllowed(string $origin): bool {
        return $origin !== '' && ($this->isAnyAllowed() || in_array($origin, $this->getAllowedOrigins(), true));
    }

    public function isAnyAllowed(): bool {
        return in_array('*', $this->getAllowedOrigins(), true);
    }
}
