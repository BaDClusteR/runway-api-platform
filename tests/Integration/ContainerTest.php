<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Integration;

use ApiPlatform\Api;
use ApiPlatform\Controller\Controller404Decorator;
use ApiPlatform\Controller\ExceptionControllerDecorator;
use ApiPlatform\Controller\IApiController;
use ApiPlatform\Controller\MethodNotAllowedControllerDecorator;
use ApiPlatform\Core\Singleton\Auth;
use ApiPlatform\Core\Singleton\IAuth;
use ApiPlatform\IApi;
use ApiPlatform\OpenAPI\IOpenApiGenerator;
use ApiPlatform\Tests\Support\ArrayPath;
use PHPUnit\Framework\TestCase;
use Runway\Controller\IController404;
use Runway\Controller\IExceptionController;
use Runway\Controller\IMethodNotAllowedController;
use Runway\Singleton\Container;

/**
 * The services of config/*.yaml.
 */
class ContainerTest extends TestCase {
    use ArrayPath;

    public function testServices(): void {
        $container = Container::getInstance();

        self::assertInstanceOf(Api::class, $container->getService(IApi::class));
        self::assertInstanceOf(IApiController::class, $container->getService(IApiController::class));
        self::assertInstanceOf(Auth::class, $container->getService(IAuth::class));
        self::assertTrue($container->getService(IAuth::class)->isEnabled());
    }

    /**
     * The API paths get the JSON errors.
     */
    public function testDecorators(): void {
        $container = Container::getInstance();

        self::assertInstanceOf(Controller404Decorator::class, $container->getService(IController404::class));
        self::assertInstanceOf(ExceptionControllerDecorator::class, $container->getService(IExceptionController::class));
        self::assertInstanceOf(
            MethodNotAllowedControllerDecorator::class,
            $container->getService(IMethodNotAllowedController::class)
        );
    }

    public function testVersion(): void {
        $api = Container::getInstance()->getService(IApi::class);
        self::assertInstanceOf(IApi::class, $api);
        self::assertSame('2.0.0', $api->getVersion());
    }

    public function testSpecInfo(): void {
        $generator = Container::getInstance()->getService(IOpenApiGenerator::class);
        self::assertInstanceOf(IOpenApiGenerator::class, $generator);
        $spec = $generator->generateOpenApiSpec();

        self::assertSame(['title' => 'API', 'description' => '', 'version' => '2.0.0'], self::at($spec, 'info'));
    }
}
