<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Support;

use Runway\Request\IResponseRead;
use Runway\Request\Request;

/**
 * The requests handled in the process of the test: the globals of PHP, the Runway request with the real container.
 */
trait InProcessRequests {
    /**
     * @param array<string, string> $headers E.g. ["Origin" => "https://app.example"].
     * @param array<string, string> $cookies
     * @param array<string, mixed>  $form    $_POST.
     */
    protected function request(
        string $method,
        string $uri,
        string $body = '',
        array  $headers = [],
        array  $cookies = [],
        array  $form = []
    ): IResponseRead {
        $server = $_SERVER;

        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;

        foreach ($headers as $name => $value) {
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $_GET = [];
        parse_str((string)parse_url($uri, PHP_URL_QUERY), $_GET);
        $_POST = $form;
        $_COOKIE = $cookies;

        try {
            $request = Request::resetInstance();
            $request->setBody($body);

            try {
                return $request->process();
            } finally {
                // Request::process() sets the error handler of the request.
                restore_error_handler();
            }
        } finally {
            $_SERVER = $server;
            $_GET = $_POST = $_COOKIE = [];
        }
    }

    /**
     * @return array<mixed>
     */
    protected function getJson(IResponseRead $response): array {
        $data = json_decode($response->getBody(), true);
        self::assertIsArray($data, $response->getBody());

        return $data;
    }
}
