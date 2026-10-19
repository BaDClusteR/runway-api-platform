<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Runway\Request\IResponseRead;
use Runway\Request\Request;

/**
 * The whole request handling: the Runway router, the API route provider, the controller and the endpoint (see
 * tests/Fixture/ChatEndpoints.php).
 */
class ApiRequestTest extends TestCase {
    /** The form fields of the next request (see request()). */
    protected array $form = [];

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('successfulRequestProvider')]
    public function testSuccessfulRequest(string $method, string $uri, array $expected, string $body = ''): void {
        $response = $this->request($method, $uri, $body);

        self::assertSame(200, $response->getCode(), $response->getBody());
        self::assertSame('application/json', $response->getHeaders()['Content-Type']);
        self::assertSame($expected, json_decode($response->getBody(), true)['result']);
    }

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
            'bool false'            => ['GET', '/api/chats/42/flags?pinned=false', ['chatId' => 42, 'pinned' => false]],
            'bool true'             => ['GET', '/api/chats/42/flags?pinned=1', ['chatId' => 42, 'pinned' => true]],
            // The query string, whatever the method.
            'query of DELETE'       => [
                'DELETE',
                '/api/chats/42/files?path=a%2Fb%20c.txt',
                ['chatId' => 42, 'path' => 'a/b c.txt'],
            ],
            'query of POST'         => ['POST', '/api/chats/42/chunks?offset=8', ['chatId' => 42, 'offset' => 8]],
        ];
    }

    /**
     * A query parameter of a POST request can come in the form, e.g. with the uploaded file.
     */
    public function testQueryParameterInForm(): void {
        $this->form = ['offset' => '16'];
        $response = $this->request('POST', '/api/chats/42/chunks');

        self::assertSame(200, $response->getCode(), $response->getBody());
        self::assertSame(['chatId' => 42, 'offset' => 16], json_decode($response->getBody(), true)['result']);
    }

    #[DataProvider('notFoundProvider')]
    public function testNotFound(string $uri): void {
        $response = $this->request('GET', $uri);

        self::assertSame(404, $response->getCode());
        self::assertSame('application/json', $response->getHeaders()['Content-Type']);
        self::assertSame(404, json_decode($response->getBody(), true)['errors'][0]['code']);
    }

    public static function notFoundProvider(): array {
        return [
            'unknown endpoint'       => ['/api/contacts'],
            'not an int'             => ['/api/chats/abc'],
            'inline requirement'     => ['/api/chats/42/years/26'],
            'too many segments'      => ['/api/chats/42/chunks/3/more'],
            'prefix only'            => ['/api'],
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
        self::assertSame(405, json_decode($response->getBody(), true)['errors'][0]['code']);
    }

    public function testOptions(): void {
        $response = $this->request('OPTIONS', '/api/chats/42');

        self::assertSame(204, $response->getCode());
        self::assertSame('GET, HEAD, OPTIONS', $response->getHeaders()['Allow']);
        // No CORS without API_ALLOW_ORIGIN.
        self::assertArrayNotHasKey('Access-Control-Allow-Origin', $response->getHeaders());
    }

    public function testValidationError(): void {
        $response = $this->request('GET', '/api/chats/42/messages?number=abc');

        self::assertSame(400, $response->getCode());
        self::assertStringContainsString('number is not a number', $response->getBody());
    }

    public function testInvalidBool(): void {
        $response = $this->request('GET', '/api/chats/42/flags?pinned=abc');

        self::assertSame(400, $response->getCode());
        self::assertStringContainsString('pinned is not a boolean', $response->getBody());
    }

    public function testInvalidBody(): void {
        $response = $this->request('PATCH', '/api/chats/42/messages/7/meta', '{"meta":');

        self::assertSame(422, $response->getCode());
    }

    public function testPrivateEndpointRequiresAuthorization(): void {
        self::assertSame(401, $this->request('GET', '/api/chats/42/private')->getCode());
    }

    /**
     * A change with the session cookie from a page of another site (CSRF) is rejected, a request of a script (without
     * the cookie) or of the site itself is not.
     */
    #[DataProvider('originProvider')]
    public function testOrigin(?string $cookie, string $origin, int $expectedCode): void {
        $_SERVER['HTTP_HOST'] = 'archive.example';
        $_SERVER['HTTP_ORIGIN'] = $origin;
        $_COOKIE = ($cookie !== null) ? ['api_session' => $cookie] : [];

        try {
            $response = $this->request('PATCH', '/api/chats/42/messages/7/meta', '{"meta": {"a": "b"}}');
        } finally {
            unset($_SERVER['HTTP_HOST'], $_SERVER['HTTP_ORIGIN']);
            $_COOKIE = [];
        }

        self::assertSame($expectedCode, $response->getCode());
    }

    public static function originProvider(): array {
        return [
            'another site with the cookie' => ['token', 'https://evil.example', 403],
            'the site itself'              => ['token', 'https://archive.example', 200],
            'another site without cookie'  => [null, 'https://evil.example', 200],
        ];
    }

    protected function request(string $method, string $uri, string $body = ''): IResponseRead {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_GET = [];
        parse_str((string)parse_url($uri, PHP_URL_QUERY), $_GET);
        $_POST = $this->form;
        $this->form = [];

        $request = Request::resetInstance();
        $request->setBody($body);

        try {
            return $request->process();
        } finally {
            // Request::process() sets the error handler of the request.
            restore_error_handler();
        }
    }
}
