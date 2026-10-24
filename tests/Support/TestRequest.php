<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Support;

use Runway\Request\Request;

/**
 * A Runway request of the unit tests: "GET https://api.example/" from 203.0.113.5, the rest is set by the setters.
 */
final class TestRequest {
    /**
     * @param array<string, string> $headers
     */
    public static function create(string $method = 'GET', string $path = '/', array $headers = []): Request {
        return new Request(new EmptyRequestParameters(), new LoggerSpy())
            ->setMethod($method)
            ->setPath($path)
            ->setHeaders($headers)
            ->setRequestId('request-id');
    }
}
