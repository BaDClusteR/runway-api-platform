<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Support;

use Runway\Request\Parameters\Provider\IRequestParametersProvider;

/**
 * A request without anything: the tests set what they need (see TestRequest).
 */
final class EmptyRequestParameters implements IRequestParametersProvider {
    public function getMethod(): string {
        return 'GET';
    }

    public function getHost(): string {
        return 'api.example';
    }

    public function getPort(): int {
        return 443;
    }

    public function getIpAddress(): string {
        return '203.0.113.5';
    }

    public function getProtocol(): string {
        return 'https';
    }

    public function getPath(): string {
        return '/';
    }

    public function getRequestUri(): string {
        return '/';
    }

    public function getBody(): string {
        return '';
    }

    public function getGetParameters(): array {
        return [];
    }

    public function getPostParameters(): array {
        return [];
    }

    public function getServerParameters(): array {
        return [];
    }

    public function getHeaders(): array {
        return [];
    }

    public function getCookies(): array {
        return [];
    }

    public function getArguments(): array {
        return [];
    }

    public function getFiles(): array {
        return [];
    }
}
