<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Support;

/**
 * A response of the PHP built-in server (see HttpClient).
 */
final readonly class HttpResponse {
    /**
     * @param array<string, string[]> $headers Lowercase name => values (e.g. several "set-cookie").
     */
    public function __construct(
        public int    $code,
        public array  $headers,
        public string $body
    ) {}

    public function getHeader(string $name): ?string {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    /**
     * @return mixed The decoded JSON body.
     */
    public function getJson(): mixed {
        return json_decode($this->body, true);
    }
}
