<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\E2E;

use ApiPlatform\Tests\Support\HttpClient;
use ApiPlatform\Tests\Support\HttpResponse;
use ApiPlatform\Tests\Support\HttpServer;
use ApiPlatform\Tests\Support\ArrayPath;
use PHPUnit\Framework\TestCase;

/**
 * The API behind the PHP built-in server: the headers and the cookies as a browser gets them, the uploaded files, the
 * session flow, the spec (see tests/E2E/server.php).
 */
class HttpApiTest extends TestCase {
    use ArrayPath;

    protected const string PASSWORD = 'correct horse battery';

    protected static HttpServer $server;

    protected static HttpClient $client;

    public static function setUpBeforeClass(): void {
        static::$server = new HttpServer();
        static::$server->start();
        static::$client = new HttpClient(static::$server->getBaseUrl());

        static::$server->getDatabase()->exec(
            "INSERT INTO e2e_options (name, value) VALUES ('auth_login', 'me'), ('auth_password_hash', '"
            . password_hash(static::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]) . "')"
        );
    }

    public static function tearDownAfterClass(): void {
        static::$server->stop();
    }

    protected function setUp(): void {
        // The limit of the failed logins.
        static::$server->getDatabase()->exec('DELETE FROM e2e_login_attempts');
    }

    public function testBrowserSession(): void {
        self::assertSame(401, $this->request('GET', '/api/chats/42/private')->code);
        self::assertSame(401, $this->request('GET', '/api/auth/check')->code);

        $response = $this->logIn();
        $setCookie = (string)$response->getHeader('Set-Cookie');

        // HttpOnly: the scripts of the page cannot read it, SameSite=Strict: other sites cannot send it. Not Secure
        // over HTTP.
        self::assertMatchesRegularExpression('/^api_session=[0-9a-f]{64}; expires=[^;]+; Max-Age=\d+; path=\/; HttpOnly; SameSite=Strict$/', $setCookie);
        self::assertNull(self::at($response->getJson(), 'token'));

        $cookie = (string)strstr($setCookie, ';', true);
        self::assertSame(200, $this->request('GET', '/api/chats/42/private', headers: ['Cookie' => $cookie])->code);
        // For nginx "auth_request".
        self::assertSame(204, $this->request('GET', '/api/auth/check', headers: ['Cookie' => $cookie])->code);

        $response = $this->request('POST', '/api/auth/logout', headers: ['Cookie' => $cookie]);

        self::assertSame(204, $response->code);
        self::assertStringStartsWith('api_session=deleted; expires=Thu, 01 Jan 1970', (string)$response->getHeader('Set-Cookie'));
        self::assertSame(401, $this->request('GET', '/api/chats/42/private', headers: ['Cookie' => $cookie])->code);
    }

    public function testScriptSession(): void {
        $token = self::at($this->logIn(useCookie: false)->getJson(), 'token');

        self::assertIsString($token);
        self::assertSame(200, $this->request('GET', '/api/chats/42/private', headers: ['X-Token' => $token])->code);
    }

    /**
     * A page of another site cannot make a change with the session cookie of the browser (CSRF).
     */
    public function testRequestOfAnotherSite(): void {
        $cookie = (string)strstr((string)$this->logIn()->getHeader('Set-Cookie'), ';', true);
        $headers = ['Cookie' => $cookie, 'Content-Type' => 'text/plain'];
        $body = '{"meta": {"tag": "work"}}';

        $response = $this->request('PATCH', '/api/chats/42/messages/7/meta', $body, [...$headers, 'Origin' => 'https://evil.example']);
        self::assertSame(403, $response->code);
        self::assertSame('The request is made by another site.', self::at($response->getJson(), 'errors', 0, 'message'));

        $response = $this->request(
            'PATCH',
            '/api/chats/42/messages/7/meta',
            $body,
            [...$headers, 'Origin' => static::$server->getBaseUrl()]
        );
        self::assertSame(200, $response->code, $response->body);
    }

    public function testTooManyFailedLogins(): void {
        for ($i = 0; $i < 5; $i++) {
            self::assertSame(401, $this->request('POST', '/api/auth/login', '{"login": "me", "password": "wrong password"}')->code);
        }

        self::assertSame(429, $this->logIn()->code);
    }

    /**
     * A real upload: the endpoint gets the uploaded file (see is_uploaded_file()).
     */
    public function testFileUpload(): void {
        $response = static::$client->postForm('/api/misc/upload', [], ['file' => ['report.pdf', '%PDF-1.4']]);

        self::assertSame(200, $response->code, $response->body);
        self::assertSame(['name' => 'report.pdf', 'preview' => null], self::at($response->getJson(), 'result'));

        $response = static::$client->postForm(
            '/api/misc/upload',
            [],
            ['file' => ['report.pdf', '%PDF-1.4'], 'preview' => ['preview.png', 'PNG']]
        );

        self::assertSame(['name' => 'report.pdf', 'preview' => 'preview.png'], self::at($response->getJson(), 'result'));
    }

    /**
     * The file field of the form is left empty: the optional file is not provided, the required one is a client error.
     */
    public function testEmptyFileField(): void {
        $response = static::$client->postForm('/api/misc/upload', [], ['file' => ['report.pdf', '%PDF'], 'preview' => ['', '']]);

        self::assertSame(['name' => 'report.pdf', 'preview' => null], self::at($response->getJson(), 'result'));

        $response = static::$client->postForm('/api/misc/upload', [], ['file' => ['', '']]);

        self::assertSame(400, $response->code);
        self::assertSame("Required file 'file' is not found in the request", self::at($response->getJson(), 'errors', 0, 'message'));
    }

    /**
     * The query parameters can be the fields of the form with the file.
     */
    public function testFormFields(): void {
        $response = static::$client->postForm('/api/chats/42/chunks', ['offset' => '16'], ['chunk' => ['a.bin', 'abc']]);

        self::assertSame(['chatId' => 42, 'offset' => 16], self::at($response->getJson(), 'result'));
    }

    public function testSchema(): void {
        $response = $this->request('GET', '/api/schema');

        self::assertSame(200, $response->code);
        self::assertSame('application/json', $response->getHeader('Content-Type'));

        $spec = $response->getJson();
        self::assertIsArray($spec);
        self::assertSame('3.0.0', self::at($spec, 'openapi'));
        self::assertSame('2.0.0', self::at($spec, 'info', 'version'));
        self::assertArrayHasKey('/api/chats/{chatId}/chunks/{index}', self::arrayAt($spec, 'paths'));
        self::assertArrayHasKey('/api/auth/login', self::arrayAt($spec, 'paths'));
        self::assertArrayNotHasKey('/api/schema', self::arrayAt($spec, 'paths'));
    }

    public function testErrors(): void {
        $response = $this->request('GET', '/api/unknown');

        self::assertSame(404, $response->code);
        self::assertSame('application/json', $response->getHeader('Content-Type'));
        self::assertSame('nosniff', $response->getHeader('X-Content-Type-Options'));
        self::assertSame('no-store', $response->getHeader('Cache-Control'));
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string)$response->getHeader('X-Request-Id'));
        self::assertSame($response->getHeader('X-Request-Id'), self::at($response->getJson(), 'requestId'));

        $response = $this->request('DELETE', '/api/chats/42');

        self::assertSame(405, $response->code);
        self::assertSame('GET, HEAD, OPTIONS', $response->getHeader('Allow'));
    }

    public function testPreflight(): void {
        $response = $this->request('OPTIONS', '/api/chats/42/messages/7/meta', headers: [
            'Origin'                        => 'https://app.example',
            'Access-Control-Request-Method' => 'PATCH',
        ]);

        self::assertSame(204, $response->code);
        self::assertSame('https://app.example', $response->getHeader('Access-Control-Allow-Origin'));
        self::assertSame('GET, POST, PATCH, OPTIONS', $response->getHeader('Access-Control-Allow-Methods'));
        self::assertSame('Content-Type, X-Token', $response->getHeader('Access-Control-Allow-Headers'));
        self::assertSame('Origin', $response->getHeader('Vary'));
    }

    protected function logIn(bool $useCookie = true): HttpResponse {
        return $this->request(
            'POST',
            '/api/auth/login',
            (string)json_encode(['login' => 'me', 'password' => static::PASSWORD, 'useCookie' => $useCookie])
        );
    }

    /**
     * @param array<string, string> $headers
     */
    protected function request(string $method, string $path, string $body = '', array $headers = []): HttpResponse {
        $response = static::$client->request($method, $path, $body, $headers);

        // The details of a failure.
        if ($response->code >= 500) {
            self::fail("$method $path: $response->code\n$response->body\n" . static::$server->getLog());
        }

        return $response;
    }
}
