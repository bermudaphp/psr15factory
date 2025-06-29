<?php

declare(strict_types=1);

namespace Bermuda\MiddlewareFactory\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Container\ContainerInterface;
use Bermuda\MiddlewareFactory\Adapter\RequestHandlerAdapter;
use Bermuda\MiddlewareFactory\Strategy\ClassNameStrategy;

/**
 * Tests for ClassNameStrategy class - testing real class resolution and instantiation
 */
class ClassNameStrategyTest extends TestCase
{
    private ClassNameStrategy $strategy;
    private ContainerInterface|MockObject $container;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->strategy = new ClassNameStrategy($this->container);
    }

    public function testMakeMiddlewareWithNonString(): void
    {
        $result = $this->strategy->makeMiddleware(123);
        $this->assertNull($result);
    }

    public function testMakeMiddlewareWithNonExistentClass(): void
    {
        $result = $this->strategy->makeMiddleware('NonExistentClass');
        $this->assertNull($result);
    }

    public function testMakeMiddlewareWithMiddlewareClass(): void
    {
        $middlewareClass = TestMiddleware::class;
        $middleware = new TestMiddleware();

        $this->container->expects($this->once())
            ->method('has')
            ->with($middlewareClass)
            ->willReturn(true);

        $this->container->expects($this->once())
            ->method('get')
            ->with($middlewareClass)
            ->willReturn($middleware);

        $result = $this->strategy->makeMiddleware($middlewareClass);
        $this->assertSame($middleware, $result);
    }

    public function testMakeMiddlewareWithRequestHandlerClass(): void
    {
        $handlerClass = TestRequestHandler::class;
        $handler = new TestRequestHandler();

        // TestRequestHandler only implements RequestHandlerInterface, not MiddlewareInterface
        // So only one has() call should be made
        $this->container->expects($this->once())
            ->method('has')
            ->with($handlerClass)
            ->willReturn(true);

        $this->container->expects($this->once())
            ->method('get')
            ->with($handlerClass)
            ->willReturn($handler);

        $result = $this->strategy->makeMiddleware($handlerClass);
        $this->assertInstanceOf(RequestHandlerAdapter::class, $result);
    }

    public function testMakeMiddlewareWithNonMiddlewareClass(): void
    {
        $regularClass = \stdClass::class;

        // stdClass doesn't implement MiddlewareInterface or RequestHandlerInterface
        // so has() should never be called
        $this->container->expects($this->never())
            ->method('has');

        $this->container->expects($this->never())
            ->method('get');

        $result = $this->strategy->makeMiddleware($regularClass);
        $this->assertNull($result);
    }

    public function testMakeMiddlewareWithClassNotInContainer(): void
    {
        $middlewareClass = TestMiddleware::class;

        $this->container->expects($this->once())
            ->method('has')
            ->with($middlewareClass)
            ->willReturn(false);

        $this->container->expects($this->never())
            ->method('get');

        $result = $this->strategy->makeMiddleware($middlewareClass);
        $this->assertNull($result);
    }

    public function testMakeMiddlewareWithContainerException(): void
    {
        $middlewareClass = TestMiddleware::class;

        $this->container->expects($this->once())
            ->method('has')
            ->with($middlewareClass)
            ->willReturn(true);

        $this->container->expects($this->once())
            ->method('get')
            ->with($middlewareClass)
            ->willThrowException(new \Exception('Container error'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Container error');

        $this->strategy->makeMiddleware($middlewareClass);
    }

    public function testCreateFromContainer(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $strategy = ClassNameStrategy::createFromContainer($container);
        $this->assertInstanceOf(ClassNameStrategy::class, $strategy);
    }
}

/**
 * Test middleware implementation
 */
class TestMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}

/**
 * Test request handler implementation
 */
class TestRequestHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new \LogicException('This method should not be called in tests');
    }
}