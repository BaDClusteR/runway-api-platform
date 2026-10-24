<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Unit\Core;

use ApiPlatform\Attribute\Parameter;
use ApiPlatform\Core\Provider\EndpointMethodParameterValue\EndpointMethodParameterValueProvider;
use ApiPlatform\DTO\ApiEndpointArgumentFileDTO;
use ApiPlatform\DTO\ApiMethodParameterDTO;
use ApiPlatform\DTO\ApiRequestDTO;
use ApiPlatform\Exception\BadRequestException;
use ApiPlatform\Exception\InternalErrorException;
use ApiPlatform\Exception\RequiredParameterNotProvidedException;
use ApiPlatform\Tests\Support\TestRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Runway\Request\Parameters\DTO\FileDTO;
use Runway\Request\Request;

class EndpointMethodParameterValueProviderTest extends TestCase {
    public function testQueryParameter(): void {
        $request = TestRequest::create()->setGetParameters(['number' => '10']);

        self::assertSame('10', $this->getValue($request, $this->parameter(Parameter::SOURCE_QUERY, 'number')));
    }

    /**
     * The query string wins over the form.
     */
    public function testQueryParameterOfPost(): void {
        $request = TestRequest::create('POST')
            ->setGetParameters(['offset' => '1'])
            ->setPostParameters(['offset' => '2', 'limit' => '3']);

        self::assertSame('1', $this->getValue($request, $this->parameter(Parameter::SOURCE_QUERY, 'offset')));
        self::assertSame('3', $this->getValue($request, $this->parameter(Parameter::SOURCE_QUERY, 'limit')));
    }

    /**
     * PHP fills $_POST only for a form, but a GET request has no form anyway.
     */
    public function testFormIsNotQueryOfGet(): void {
        $request = TestRequest::create()->setPostParameters(['limit' => '3']);

        $this->expectException(RequiredParameterNotProvidedException::class);
        $this->expectExceptionMessage('Required parameter limit is not provided.');

        $this->getValue($request, $this->parameter(Parameter::SOURCE_QUERY, 'limit'));
    }

    public function testQueryParameterDefaultValue(): void {
        self::assertSame(
            'before',
            $this->getValue(TestRequest::create(), $this->parameter(Parameter::SOURCE_QUERY, 'direction', true, 'before'))
        );
        // Even null.
        self::assertNull(
            $this->getValue(TestRequest::create(), $this->parameter(Parameter::SOURCE_QUERY, 'number', true, null))
        );
    }

    public function testEmptyQueryParameterIsProvided(): void {
        $request = TestRequest::create()->setGetParameters(['q' => '']);

        self::assertSame('', $this->getValue($request, $this->parameter(Parameter::SOURCE_QUERY, 'q', true, 'default')));
    }

    public function testRequiredQueryParameter(): void {
        $this->expectException(RequiredParameterNotProvidedException::class);
        $this->expectExceptionCode(400);

        $this->getValue(TestRequest::create(), $this->parameter(Parameter::SOURCE_QUERY, 'number'));
    }

    public function testBodyParameter(): void {
        $request = $this->apiRequest(body: ['meta' => ['tag' => 'work'], 'note' => null]);

        self::assertSame(['tag' => 'work'], $this->getValue(TestRequest::create(), $this->parameter(Parameter::SOURCE_BODY, 'meta'), $request));
        // A null is a value.
        self::assertNull($this->getValue(TestRequest::create(), $this->parameter(Parameter::SOURCE_BODY, 'note', true, 'default'), $request));
    }

    public function testBodyParameterDefaultValue(): void {
        self::assertSame(
            'default',
            $this->getValue(TestRequest::create(), $this->parameter(Parameter::SOURCE_BODY, 'note', true, 'default'))
        );
    }

    public function testRequiredBodyParameter(): void {
        $this->expectException(RequiredParameterNotProvidedException::class);
        $this->expectExceptionMessage('Required parameter note is not provided.');

        $this->getValue(TestRequest::create(), $this->parameter(Parameter::SOURCE_BODY, 'note'));
    }

    /**
     * The body is not the form: e.g. a urlencoded form is not taken for the JSON.
     */
    public function testBodyIsNotForm(): void {
        $request = TestRequest::create('POST')->setPostParameters(['note' => 'a']);

        $this->expectException(RequiredParameterNotProvidedException::class);

        $this->getValue($request, $this->parameter(Parameter::SOURCE_BODY, 'note'));
    }

    public function testPathParameter(): void {
        $request = $this->apiRequest(pathParameters: ['chatId' => '42']);

        self::assertSame('42', $this->getValue(TestRequest::create(), $this->parameter(Parameter::SOURCE_PATH, 'chatId'), $request));
    }

    public function testPathParameterWithoutValue(): void {
        $this->expectException(InternalErrorException::class);
        $this->expectExceptionMessage("Path placeholder {chatId} of argument 'chatIdArgument' has no value.");

        $this->getValue(TestRequest::create(), $this->parameter(Parameter::SOURCE_PATH, 'chatId'));
    }

    /**
     * An argument without a source gets its default value even if the request has such a parameter.
     */
    public function testArgumentWithoutSource(): void {
        $request = TestRequest::create()->setGetParameters(['isInternal' => '1']);

        self::assertFalse($this->getValue($request, $this->parameter('', 'isInternal', true, false)));
    }

    public function testInvalidSource(): void {
        $this->expectException(InternalErrorException::class);
        $this->expectExceptionMessage("Argument 'sessionArgument' has invalid source: 'cookie'.");

        $this->getValue(TestRequest::create(), $this->parameter('cookie', 'session'));
    }

    public function testFile(): void {
        $file = $this->getValue(
            TestRequest::create(),
            $this->parameter(Parameter::SOURCE_FILE, 'photo'),
            $this->apiRequest(files: ['photo' => $this->file('/tmp/php123')])
        );

        self::assertEquals(new ApiEndpointArgumentFileDTO('photo.jpg', 'image/jpeg', '/tmp/php123', 1024), $file);
    }

    /**
     * The endpoint can move the file: the path must be of an uploaded one, not e.g. "/etc/passwd".
     */
    public function testFileIsNotUploaded(): void {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage("File 'photo' is not uploaded");

        $this->getValue(
            TestRequest::create(),
            $this->parameter(Parameter::SOURCE_FILE, 'photo'),
            $this->apiRequest(files: ['photo' => $this->file('/etc/passwd')]),
            new EndpointMethodParameterValueProvider(TestRequest::create())
        );
    }

    public function testRequiredFile(): void {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage("Required file 'photo' is not found in the request");

        $this->getValue(TestRequest::create(), $this->parameter(Parameter::SOURCE_FILE, 'photo'));
    }

    public function testOptionalFile(): void {
        self::assertNull($this->getValue(TestRequest::create(), $this->parameter(Parameter::SOURCE_FILE, 'photo', true, null)));
    }

    /**
     * The file field of the form is left empty.
     */
    public function testEmptyFileField(): void {
        $request = $this->apiRequest(files: ['photo' => $this->file('', UPLOAD_ERR_NO_FILE)]);

        self::assertNull($this->getValue(TestRequest::create(), $this->parameter(Parameter::SOURCE_FILE, 'photo', true, null), $request));

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage("Required file 'photo' is not found in the request");

        $this->getValue(TestRequest::create(), $this->parameter(Parameter::SOURCE_FILE, 'photo'), $request);
    }

    /**
     * @param class-string<\Throwable> $exception
     */
    #[DataProvider('uploadErrorProvider')]
    public function testUploadError(int $error, string $exception, int $code, string $message): void {
        try {
            $this->getValue(
                TestRequest::create(),
                $this->parameter(Parameter::SOURCE_FILE, 'photo', true, null),
                $this->apiRequest(files: ['photo' => $this->file('/tmp/php123', $error)])
            );
            self::fail('No exception.');
        } catch (BadRequestException|InternalErrorException $e) {
            self::assertInstanceOf($exception, $e);
            self::assertSame($code, $e->getCode());
            self::assertSame($message, $e->getMessage());
        }
    }

    /**
     * @return array<string, array{int, class-string<\Throwable>, int, string}>
     */
    public static function uploadErrorProvider(): array {
        return [
            'ini size'   => [
                UPLOAD_ERR_INI_SIZE,
                BadRequestException::class,
                400,
                "File 'photo': file size exceeds the maximum upload size",
            ],
            'form size'  => [
                UPLOAD_ERR_FORM_SIZE,
                BadRequestException::class,
                400,
                "File 'photo': file size exceeds the maximum upload size that was specified in the HTML form",
            ],
            'partial'    => [
                UPLOAD_ERR_PARTIAL,
                BadRequestException::class,
                400,
                "File 'photo': file was only partially uploaded",
            ],
            'no tmp dir' => [
                UPLOAD_ERR_NO_TMP_DIR,
                InternalErrorException::class,
                500,
                "Cannot upload file 'photo': missing a temporary folder",
            ],
            'cant write' => [
                UPLOAD_ERR_CANT_WRITE,
                InternalErrorException::class,
                500,
                "Cannot upload file 'photo': unable to write the file to the disk",
            ],
            'extension'  => [
                UPLOAD_ERR_EXTENSION,
                InternalErrorException::class,
                500,
                "Cannot upload file 'photo': upload stopped by PHP extension",
            ],
            'unknown'    => [100, InternalErrorException::class, 500, "Cannot upload file 'photo': unknown error"],
        ];
    }

    protected function getValue(
        Request                               $request,
        ApiMethodParameterDTO                 $parameter,
        ?ApiRequestDTO                        $apiRequest = null,
        ?EndpointMethodParameterValueProvider $provider = null
    ): mixed {
        return ($provider ?? $this->provider($request))
            ->getEndpointMethodParameterValue($parameter, $apiRequest ?? $this->apiRequest());
    }

    /**
     * Every file is "uploaded", except the tests of the check.
     */
    protected function provider(Request $request): EndpointMethodParameterValueProvider {
        return new class ($request) extends EndpointMethodParameterValueProvider {
            protected function isUploadedFile(string $path): bool {
                return true;
            }
        };
    }

    protected function parameter(
        string $source,
        string $name,
        bool   $hasDefaultValue = false,
        mixed  $defaultValue = null
    ): ApiMethodParameterDTO {
        return new ApiMethodParameterDTO($source, $name, "{$name}Argument", $hasDefaultValue, $defaultValue);
    }

    /**
     * @param array<string, string>  $pathParameters
     * @param array<mixed>           $body
     * @param array<string, FileDTO> $files
     */
    protected function apiRequest(array $pathParameters = [], array $body = [], array $files = []): ApiRequestDTO {
        return new ApiRequestDTO('GET', $pathParameters, $body, $files);
    }

    protected function file(string $tmpName, int $error = UPLOAD_ERR_OK): FileDTO {
        return new FileDTO('photo.jpg', 'image/jpeg', 1024, $tmpName, (string)$error, 'photo.jpg');
    }
}
