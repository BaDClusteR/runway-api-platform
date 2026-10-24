<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Integration;

use ApiPlatform\Tests\Support\InProcessRequests;
use PHPUnit\Framework\Attributes\DataProvider;
use ApiPlatform\Tests\Support\ArrayPath;
use PHPUnit\Framework\TestCase;

/**
 * The whole request handling: the Runway router, the API route provider, the controller and the endpoint (see
 * tests/Fixture/ChatEndpoints.php).
 */
class ApiRequestTest extends TestCase {
    use ArrayPath;
    use InProcessRequests;

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('successfulRequestProvider')]
    public function testSuccessfulRequest(string $method, string $uri, array $expected, string $body = ''): void {
        $response = $this->request($method, $uri, $body);

        self::assertSame(200, $response->getCode(), $response->getBody());
        self::assertSame('application/json', $response->getHeaders()['Content-Type']);
        self::assertSame('nosniff', $response->getHeaders()['X-Content-Type-Options']);
        self::assertNotEmpty($response->getHeaders()['X-Request-Id']);
        self::assertSame($expected, self::at($this->getJson($response), 'result'));
    }

    /**
     * @return array<string, array{string, string, array<string, mixed>, 3?: string}>
     */
    public static function successfulRequestProvider(): array {
        return [
            'one placeholder'       => ['GET', '/api/chats/42', ['chatId' => 42]],
            'two placeholders'      => ['GET', '/api/chats/42/chunks/3', ['chatId' => 42, 'index' => 3]],
            'query parameters'      => [
                'GET',
                '/api/chats/42/messages?number=10&direction=after',
                ['chatId' => 42, 'number' => 10, 'direction' => 'after', 'isInternal' => false],
            ],
            'default values'        => [
                'GET',
                '/api/chats/42/messages',
                ['chatId' => 42, 'number' => null, 'direction' => 'before', 'isInternal' => false],
            ],
            // Not taken from the request.
            'argument without source' => [
                'GET',
                '/api/chats/42/messages?isInternal=1',
                ['chatId' => 42, 'number' => null, 'direction' => 'before', 'isInternal' => false],
            ],
            'static segment wins'   => ['GET', '/api/chats/42/messages/latest', ['chatId' => 42, 'latest' => true]],
            'body'                  => [
                'PATCH',
                '/api/chats/42/messages/7/meta',
                ['chatId' => 42, 'messageId' => 7, 'meta' => ['tag' => 'work']],
                '{"meta": {"tag": "work"}}',
            ],
            'renamed path argument' => [
                'GET',
                '/api/chats/42/files/My%20report.pdf',
                ['chatId' => 42, 'filename' => 'My report.pdf'],
            ],
            'second endpoint'       => [
                'GET',
                '/api/chats/42/attachments/photo.jpg',
                ['chatId' => 42, 'filename' => 'photo.jpg'],
            ],
            'inline requirement'    => ['GET', '/api/chats/42/years/2026', ['chatId' => 42, 'year' => '2026']],
            'negative id'           => ['GET', '/api/misc/items/-5', ['id' => -5]],
            'float placeholders'    => [
                'POST',
                '/api/misc/points/1.5/2/true',
                // 2.0 is 2 in JSON.
                ['x' => 1.5, 'y' => 2, 'visible' => true],
            ],
            'bool false'            => ['GET', '/api/chats/42/flags?pinned=false', ['chatId' => 42, 'pinned' => false]],
            'bool true'             => ['GET', '/api/chats/42/flags?pinned=1', ['chatId' => 42, 'pinned' => true]],
            // The query string, whatever the method.
            'query of DELETE'       => [
                'DELETE',
                '/api/chats/42/files?path=a%2Fb%20c.txt',
                ['chatId' => 42, 'path' => 'a/b c.txt'],
            ],
            'query of POST'         => ['POST', '/api/chats/42/chunks?offset=8', ['chatId' => 42, 'offset' => 8]],
            'validated'             => ['POST', '/api/misc/validated?count=3', ['code' => 'abc', 'count' => 3], '{"code": "abc"}'],
        ];
    }

    /**
     * A query parameter of a POST request can come in the form, e.g. with the uploaded file.
     */
    public function testQueryParameterInForm(): void {
        $response = $this->request('POST', '/api/chats/42/chunks', form: ['offset' => '16']);

        self::assertSame(200, $response->getCode(), $response->getBody());
        self::assertSame(['chatId' => 42, 'offset' => 16], self::at($this->getJson($response), 'result'));
    }

    #[DataProvider('notFoundProvider')]
    public function testNotFound(string $uri): void {
        $response = $this->request('GET', $uri);

        self::assertSame(404, $response->getCode());
        self::assertSame('application/json', $response->getHeaders()['Content-Type']);
        self::assertSame(404, self::at($this->getJson($response), 'errors', 0, 'code'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notFoundProvider(): array {
        return [
            'unknown endpoint'   => ['/api/contacts'],
            'not an int'         => ['/api/chats/abc'],
            'not a float'        => ['/api/misc/points/1.5.1/2/true'],
            'not a bool'         => ['/api/misc/points/1/2/yes'],
            'inline requirement' => ['/api/chats/42/years/26'],
            'too many segments'  => ['/api/chats/42/chunks/3/more'],
            'prefix only'        => ['/api'],
        ];
    }

    public function testNonApiPathKeepsDefault404(): void {
        $response = $this->request('GET', '/chats/42');

        self::assertSame(404, $response->getCode());
        self::assertArrayNotHasKey('Content-Type', $response->getHeaders());
    }

    public function testMethodNotAllowed(): void {
        $response = $this->request('DELETE', '/api/chats/42/messages/7/meta');

        self::assertSame(405, $response->getCode());
        self::assertSame('PATCH, OPTIONS', $response->getHeaders()['Allow']);
        self::assertSame(405, self::at($this->getJson($response), 'errors', 0, 'code'));
    }

    public function testOptions(): void {
        $response = $this->request('OPTIONS', '/api/chats/42');

        self::assertSame(204, $response->getCode());
        self::assertSame('GET, HEAD, OPTIONS', $response->getHeaders()['Allow']);
        // No CORS for a request without the origin.
        self::assertArrayNotHasKey('Access-Control-Allow-Origin', $response->getHeaders());
    }

    /**
     * A CORS preflight of an allowed site (API_ALLOW_ORIGIN of tests/config/test.env).
     */
    public function testPreflight(): void {
        $response = $this->request('OPTIONS', '/api/chats/42/messages/7/meta', headers: ['Origin' => 'https://app.example']);

        self::assertSame(204, $response->getCode());
        self::assertSame('https://app.example', $response->getHeaders()['Access-Control-Allow-Origin']);
        self::assertSame('POST, GET, PUT, PATCH, DELETE, OPTIONS', $response->getHeaders()['Access-Control-Allow-Methods']);
        self::assertSame('Content-Type, X-Token', $response->getHeaders()['Access-Control-Allow-Headers']);

        $response = $this->request('OPTIONS', '/api/chats/42/messages/7/meta', headers: ['Origin' => 'https://evil.example']);

        self::assertArrayNotHasKey('Access-Control-Allow-Origin', $response->getHeaders());
    }

    public function testValidationError(): void {
        $response = $this->request('GET', '/api/chats/42/messages?number=abc');

        self::assertSame(400, $response->getCode());
        self::assertSame([['code' => 400, 'message' => 'number is not a number.']], self::at($this->getJson($response), 'errors'));
    }

    public function testValidationErrorsOfAllArguments(): void {
        $response = $this->request('POST', '/api/misc/validated?count=11', '{"code": "abcd"}');

        self::assertSame(400, $response->getCode());
        self::assertSame(
            ['code should be at most 3 characters long, 4 given.', 'count should be 10 or less, 11 given.'],
            array_column(self::arrayAt($this->getJson($response), 'errors'), 'message')
        );
    }

    /**
     * The assertions are not applied to a value of another type: a client error, not "Array to string conversion".
     */
    public function testInvalidTypeWithAssertions(): void {
        $response = $this->request('POST', '/api/misc/validated', '{"code": ["a"]}');

        self::assertSame(400, $response->getCode());
        self::assertSame([['code' => 400, 'message' => 'code is not a string.']], self::at($this->getJson($response), 'errors'));
    }

    public function testRequiredParameter(): void {
        $response = $this->request('POST', '/api/misc/validated', '{}');

        self::assertSame(400, $response->getCode());
        self::assertSame('Required parameter code is not provided.', self::at($this->getJson($response), 'errors', 0, 'message'));
    }

    public function testInvalidBool(): void {
        $response = $this->request('GET', '/api/chats/42/flags?pinned=abc');

        self::assertSame(400, $response->getCode());
        self::assertSame('pinned is not a boolean.', self::at($this->getJson($response), 'errors', 0, 'message'));
    }

    public function testInvalidBody(): void {
        $response = $this->request('PATCH', '/api/chats/42/messages/7/meta', '{"meta":');

        self::assertSame(422, $response->getCode());
    }

    /**
     * A mistake of the endpoint (an unsupported argument type): the details are only in the log.
     */
    public function testInternalErrorIsHidden(): void {
        $response = $this->request('GET', '/api/misc/mixed/a');

        self::assertSame(500, $response->getCode());
        self::assertSame(
            [['code' => 500, 'message' => 'Internal server error']],
            self::at($this->getJson($response), 'errors')
        );
    }

    public function testPrivateEndpointRequiresAuthorization(): void {
        $response = $this->request('GET', '/api/chats/42/private');

        self::assertSame(401, $response->getCode());
        self::assertSame([['code' => 401, 'message' => 'Not authorized']], self::at($this->getJson($response), 'errors'));
    }

    /**
     * A change from a page of another site (CSRF) is rejected, a request of a script (without the origin), of the site
     * itself or of an allowed site (without the session cookie) is not.
     */
    #[DataProvider('originProvider')]
    public function testOrigin(?string $cookie, ?string $origin, int $expectedCode): void {
        $response = $this->request(
            'PATCH',
            '/api/chats/42/messages/7/meta',
            '{"meta": {"a": "b"}}',
            ['Host' => 'archive.example', ...(($origin !== null) ? ['Origin' => $origin] : [])],
            ($cookie !== null) ? ['api_session' => $cookie] : []
        );

        self::assertSame($expectedCode, $response->getCode(), $response->getBody());
    }

    /**
     * @return array<string, array{string|null, string|null, int}>
     */
    public static function originProvider(): array {
        return [
            'another site with the cookie' => ['token', 'https://evil.example', 403],
            'another site without cookie'  => [null, 'https://evil.example', 403],
            'the site itself'              => ['token', 'https://archive.example', 200],
            'allowed site'                 => [null, 'https://app.example', 200],
            'allowed site with the cookie' => ['token', 'https://app.example', 403],
            'script'                       => ['token', null, 200],
        ];
    }
}
