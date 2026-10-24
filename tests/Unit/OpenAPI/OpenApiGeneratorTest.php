<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Unit\OpenAPI;

use ApiPlatform\Core\Provider\EndpointMethodParameters\EndpointMethodParametersProvider;
use ApiPlatform\Core\Provider\EndpointMethodParameterValue\EndpointMethodParameterValueProvider;
use ApiPlatform\Core\Provider\Endpoints\EndpointsProvider;
use ApiPlatform\Core\Singleton\Auth;
use ApiPlatform\DTO\ApiEndpointDTO;
use ApiPlatform\Endpoint\Schema;
use ApiPlatform\Exception\InternalErrorException;
use ApiPlatform\IApi;
use ApiPlatform\OpenAPI\Enum\OpenApiEndpointParameterTypeEnum;
use ApiPlatform\OpenAPI\OpenApiGenerator;
use ApiPlatform\OpenAPI\Provider\Arguments\OpenApiEndpointArgumentInfoProvider;
use ApiPlatform\OpenAPI\Provider\OpenApiEndpointInfoProvider;
use ApiPlatform\OpenAPI\Provider\Response\OpenApiEndpointResponseSchemaProvider;
use ApiPlatform\Tests\Fixture\Docs\ArrayWithoutItemsEndpoints;
use ApiPlatform\Tests\Fixture\Docs\DocsEndpoints;
use ApiPlatform\Tests\Fixture\Docs\PrimitivesDTO;
use ApiPlatform\Tests\Fixture\Docs\PrimitivesEndpoints;
use ApiPlatform\Tests\Fixture\Docs\TagDTO;
use ApiPlatform\Tests\Fixture\Docs\UnionReturnEndpoints;
use ApiPlatform\Tests\Fixture\InMemoryAuthStorage;
use ApiPlatform\Tests\Support\LoggerSpy;
use ApiPlatform\Tests\Support\TestRequest;
use ApiPlatform\Tests\Support\ArrayPath;
use PHPUnit\Framework\TestCase;
use Runway\Router\Compiler\RouteCompiler;

/**
 * The spec of the fixture endpoints (see tests/Fixture/Docs).
 */
class OpenApiGeneratorTest extends TestCase {
    use ArrayPath;

    public function testInfo(): void {
        $spec = $this->getSpec();

        self::assertSame('3.0.0', self::at($spec, 'openapi'));
        self::assertSame(['title' => 'Notes API', 'description' => 'The notes', 'version' => '9.9'], self::at($spec, 'info'));
        self::assertSame([['url' => '/', 'description' => '']], self::at($spec, 'servers'));
        self::assertSame(
            [
                'Token' => [
                    'type'        => 'apiKey',
                    'description' => 'Value for the X-Token header parameter.',
                    'name'        => 'X-Token',
                    'in'          => 'header',
                ],
            ],
            self::at($spec, 'components', 'securitySchemes')
        );
    }

    public function testDefaultTitle(): void {
        self::assertSame('API', self::at($this->getSpec(title: null), 'info', 'title'));
        self::assertSame('', self::at($this->getSpec(title: null), 'info', 'description'));
    }

    public function testPathsAreSorted(): void {
        self::assertSame(['/api/notes', '/api/notes/{noteId}'], array_keys(self::arrayAt($this->getSpec(), 'paths')));
        self::assertSame(['get', 'delete', 'patch'], array_keys(self::arrayAt($this->getSpec(), 'paths', '/api/notes/{noteId}')));
    }

    public function testOperation(): void {
        $operation = self::arrayAt($this->getSpec(), 'paths', '/api/notes/{noteId}', 'get');

        self::assertSame('getNote', self::at($operation, 'operationId'));
        self::assertSame(['Notes'], self::at($operation, 'tags'));
        self::assertSame('The note with its tags', self::at($operation, 'summary'));
        self::assertSame('The note with its tags', self::at($operation, 'description'));
        self::assertFalse(self::at($operation, 'deprecated'));
        // Public.
        self::assertArrayNotHasKey('security', $operation);
        self::assertArrayNotHasKey('requestBody', $operation);
    }

    /**
     * The title is the description and the operation ID by default.
     */
    public function testOperationByTitle(): void {
        $operation = self::arrayAt($this->getSpec(), 'paths', '/api/notes', 'post');

        self::assertSame('Create a note', self::at($operation, 'operationId'));
        self::assertSame('Create a note', self::at($operation, 'description'));
    }

    public function testOperationWithoutDocs(): void {
        $operation = self::arrayAt($this->getSpec(), 'paths', '/api/notes/{noteId}', 'delete');

        self::assertSame(['tags', 'responses', 'security', 'parameters', 'deprecated'], array_keys($operation));
    }

    public function testResponses(): void {
        $responses = self::arrayAt($this->getSpec(), 'paths', '/api/notes/{noteId}', 'get', 'responses');

        self::assertSame(['200', '403', '404', '500'], array_map('strval', array_keys($responses)));
        self::assertSame(
            [
                'description' => 'A note',
                'content'     => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ApiPlatform.Tests.Fixture.Docs.NoteDTO']]],
            ],
            self::at($responses, '200')
        );
        self::assertSame(
            ['description' => 'No such note', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
            self::at($responses, '404')
        );
        self::assertSame('Internal server error', self::at($responses, '500', 'description'));
    }

    /**
     * A private endpoint can respond with 401 and needs the token.
     */
    public function testPrivateEndpoint(): void {
        $operation = self::arrayAt($this->getSpec(), 'paths', '/api/notes/{noteId}', 'delete');

        self::assertSame('Unauthorized', self::at($operation, 'responses', '401', 'description'));
        self::assertSame([['Token' => []]], self::at($operation, 'security'));
    }

    /**
     * Without the authorization every endpoint is public.
     */
    public function testWithoutAuthorization(): void {
        $operation = self::arrayAt($this->getSpec(isAuthEnabled: false), 'paths', '/api/notes/{noteId}', 'delete');

        self::assertArrayNotHasKey('401', self::arrayAt($operation, 'responses'));
        self::assertArrayNotHasKey('security', $operation);
    }

    public function testEmptyResponse(): void {
        $responses = self::arrayAt($this->getSpec(), 'paths', '/api/notes/{noteId}', 'delete', 'responses');

        self::assertSame(['description' => 'Done'], self::at($responses, '204'));
        self::assertArrayNotHasKey('200', $responses);
    }

    public function testParameters(): void {
        $parameters = self::arrayAt($this->getSpec(), 'paths', '/api/notes/{noteId}', 'get', 'parameters');

        // Not the argument without a source.
        self::assertSame(['noteId', 'view', 'zoom'], array_column($parameters, 'name'));
        self::assertSame(
            ['name' => 'noteId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer', 'nullable' => false]],
            self::at($parameters, 0)
        );
        self::assertSame(
            [
                'name'            => 'view',
                'in'              => 'query',
                'description'     => 'The view',
                'example'         => 'a',
                'required'        => false,
                // NotEmpty.
                'allowEmptyValue' => false,
                'schema'          => [
                    'type'     => 'string',
                    'nullable' => false,
                    'format'   => 'date',
                    'enum'     => ['a', 'b'],
                    'default'  => 'a',
                ],
            ],
            self::at($parameters, 1)
        );
        self::assertSame(
            [
                'name'            => 'zoom',
                'in'              => 'query',
                'required'        => false,
                'allowEmptyValue' => true,
                'schema'          => ['type' => 'number', 'nullable' => true, 'minimum' => 0.5, 'maximum' => 10],
            ],
            self::at($parameters, 2)
        );
    }

    public function testRequestBody(): void {
        self::assertSame(
            [
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type'       => 'object',
                            'properties' => ['tags' => ['type' => 'array', 'nullable' => false, 'default' => []]],
                        ],
                    ],
                ],
            ],
            self::at($this->getSpec(), 'paths', '/api/notes/{noteId}', 'patch', 'requestBody')
        );
    }

    /**
     * A file is sent with the form.
     */
    public function testMultipartBody(): void {
        self::assertSame(
            [
                'content' => [
                    'multipart/form-data' => [
                        'schema' => [
                            'type'       => 'object',
                            'properties' => [
                                'title'      => ['type' => 'string', 'nullable' => false, 'minLength' => 1, 'maxLength' => 100],
                                'attachment' => ['type' => 'string', 'nullable' => false, 'format' => 'binary'],
                            ],
                            'required'   => ['title', 'attachment'],
                        ],
                    ],
                ],
            ],
            self::at($this->getSpec(), 'paths', '/api/notes', 'post', 'requestBody')
        );
    }

    public function testResponseSchemas(): void {
        $schemas = self::arrayAt($this->getSpec(), 'components', 'schemas');

        self::assertSame(
            // Not the empty DTO of "204 No Content".
            ['ApiPlatform.Tests.Fixture.Docs.NoteDTO', 'ApiPlatform.Tests.Fixture.Docs.TagDTO', 'Error', 'ErrorMessage'],
            array_keys($schemas)
        );
        self::assertSame(
            [
                'type'        => 'object',
                'description' => 'A note',
                'properties'  => [
                    'id'       => [
                        'type'        => 'integer',
                        'nullable'    => false,
                        'description' => 'The ID',
                        'example'     => 42,
                        'minimum'     => 1,
                    ],
                    'title'    => [
                        'type'        => 'string',
                        'nullable'    => false,
                        'format'      => 'text',
                        'description' => 'The title',
                        'enum'        => ['Title'],
                        'maxLength'   => 100,
                    ],
                    'tags'     => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ApiPlatform.Tests.Fixture.Docs.TagDTO']],
                    'author'   => ['nullable' => true, 'allOf' => [['$ref' => '#/components/schemas/ApiPlatform.Tests.Fixture.Docs.TagDTO']]],
                    'likes'    => ['type' => 'array', 'items' => ['type' => 'integer']],
                    'isPinned' => ['type' => 'boolean', 'nullable' => false],
                ],
            ],
            self::at($schemas, 'ApiPlatform.Tests.Fixture.Docs.NoteDTO')
        );
        self::assertSame(['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ErrorMessage']], self::at($schemas, 'Error', 'properties', 'errors'));
    }

    public function testPrimitiveItems(): void {
        self::assertSame(
            [
                'type'       => 'object',
                'properties' => [
                    // Without a type.
                    'any'    => ['nullable' => true, 'allOf' => [['type' => 'object']]],
                    'ratios' => ['type' => 'array', 'items' => ['type' => 'number']],
                    'flags'  => ['type' => 'array', 'items' => ['type' => 'boolean']],
                    // Any JSON value.
                    'values' => ['type' => 'array', 'items' => []],
                    'names'  => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
            ],
            self::at($this->getSpec(PrimitivesEndpoints::class), 'components', 'schemas', 'ApiPlatform.Tests.Fixture.Docs.PrimitivesDTO')
        );
    }

    public function testErrorSchema(): void {
        $schemas = self::arrayAt($this->getSpec(), 'components', 'schemas');

        self::assertSame(
            [
                'type'       => 'object',
                'properties' => [
                    'errors'    => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ErrorMessage']],
                    'requestId' => ['type' => 'string'],
                ],
            ],
            $schemas['Error']
        );
        self::assertSame(
            ['type' => 'object', 'properties' => ['code' => ['type' => 'integer'], 'message' => ['type' => 'string']]],
            $schemas['ErrorMessage']
        );
    }

    public function testUnionReturnType(): void {
        $this->expectException(InternalErrorException::class);
        $this->expectExceptionMessage(
            'Class ' . PrimitivesDTO::class . '|' . TagDTO::class . ' returned by ' . UnionReturnEndpoints::class
            . '::get does not exist'
        );

        $this->getSpec(UnionReturnEndpoints::class);
    }

    /**
     * The arguments taken from the request: not the ones with the default values only.
     */
    public function testArguments(): void {
        $endpoint = $this->getEndpoint('get');

        $info = $this->getInfoProvider()->getEndpointInfo($endpoint);

        self::assertSame(['noteId', 'view', 'zoom'], array_map(static fn($argument): string => $argument->name, $info->arguments));
        self::assertSame(['path', 'query', 'query'], array_map(static fn($argument): string => $argument->source, $info->arguments));
        self::assertSame(OpenApiEndpointParameterTypeEnum::TYPE_NUMBER, $info->arguments[2]->type);
        self::assertSame(0.5, $info->arguments[2]->minimum);
        self::assertSame('application/json', $info->responseMimeType);
    }

    public function testFileArgument(): void {
        $info = $this->getInfoProvider()->getEndpointInfo($this->getEndpoint('create'));

        self::assertSame(OpenApiEndpointParameterTypeEnum::TYPE_STRING, $info->arguments[1]->type);
        self::assertSame('file', $info->arguments[1]->source);
        self::assertSame('multipart/form-data', $info->responseMimeType);
        self::assertSame(1, $info->arguments[0]->minLength);
        self::assertSame(100, $info->arguments[0]->maxLength);
    }

    public function testArgumentIsNotDescribed(): void {
        $endpoint = new ApiEndpointDTO('notes/{noteId}', 'GET', DocsEndpoints::class, 'get', true);

        $this->expectException(InternalErrorException::class);
        $this->expectExceptionMessage('Argument $noteId of ' . DocsEndpoints::class . '::get() is not described.');

        $this->getArgumentInfoProvider()->getEndpointArgumentInfo($endpoint, 'noteId');
    }

    public function testUnknownArgument(): void {
        $this->expectException(InternalErrorException::class);
        $this->expectExceptionMessage('Cannot get a reflection of argument $unknown in ' . DocsEndpoints::class);

        $this->getArgumentInfoProvider()->getEndpointArgumentInfo($this->getEndpoint('get'), 'unknown');
    }

    public function testSchemaEndpointIsNotDocumented(): void {
        self::assertSame([], self::at($this->getSpec(Schema::class), 'paths'));
    }

    public function testArrayWithoutItemsType(): void {
        $this->expectException(InternalErrorException::class);
        $this->expectExceptionMessage(
            "Error while generating schema for 'ApiPlatform.Tests.Fixture.Docs.ArrayWithoutItemsDTO': field 'items' is"
            . ' array, but the items type is not defined.'
        );

        $this->getSpec(ArrayWithoutItemsEndpoints::class);
    }

    /**
     * @param class-string $class
     *
     * @return array<string, mixed> Decoded from JSON, as a client gets it.
     */
    protected function getSpec(string $class = DocsEndpoints::class, ?string $title = 'Notes API', bool $isAuthEnabled = true): array {
        $api = $this->createStub(IApi::class);
        $api->method('getVersion')->willReturn('9.9');

        $endpointsProvider = new EndpointsProvider(new RouteCompiler(), [$class]);
        $infoProvider = $this->getInfoProvider($isAuthEnabled);

        $generator = ($title !== null)
            ? new OpenApiGenerator($endpointsProvider, $infoProvider, $api, '/api/', 'X-Token', $title, 'The notes')
            : new OpenApiGenerator($endpointsProvider, $infoProvider, $api, '/api/', 'X-Token');

        $spec = json_decode((string)json_encode($generator->generateOpenApiSpec()), true);
        self::assertIsArray($spec);

        /** @var array<string, mixed> */
        return $spec;
    }

    protected function getInfoProvider(bool $isAuthEnabled = true): OpenApiEndpointInfoProvider {
        return new OpenApiEndpointInfoProvider(
            $this->getArgumentInfoProvider(),
            new OpenApiEndpointResponseSchemaProvider(new LoggerSpy()),
            new Auth(new InMemoryAuthStorage(), $isAuthEnabled)
        );
    }

    protected function getArgumentInfoProvider(): OpenApiEndpointArgumentInfoProvider {
        return new OpenApiEndpointArgumentInfoProvider(
            new EndpointMethodParametersProvider(new EndpointMethodParameterValueProvider(TestRequest::create()))
        );
    }

    protected function getEndpoint(string $method): ApiEndpointDTO {
        $endpoint = array_find(
            new EndpointsProvider(new RouteCompiler(), [DocsEndpoints::class])->getApiEndpoints(),
            static fn(ApiEndpointDTO $endpoint): bool => $endpoint->method === $method
        );
        self::assertNotNull($endpoint);

        return $endpoint;
    }
}
