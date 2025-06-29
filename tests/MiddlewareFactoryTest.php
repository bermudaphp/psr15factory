<?php

declare(strict_types=1);

namespace Bermuda\MiddlewareFactory\Tests;

use Bermuda\MiddlewareFactory\MiddlewareFactoryAwareInterface;
use Bermuda\MiddlewareFactory\MiddlewareResolutionExceptionInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Bermuda\MiddlewareFactory\Adapter\RequestHandlerAdapter;
use Bermuda\MiddlewareFactory\MiddlewareFactory;
use Bermuda\MiddlewareFactory\MiddlewareResolutionException;
use Bermuda\MiddlewareFactory\Strategy\StrategyInterface;
use Bermuda\ContainerAwareInterface;
use RuntimeException;

/**
 * Tests for MiddlewareFactory class
 */
class MiddlewareFactoryTest extends TestCase
{
    private MiddlewareFactory $factory;
    private ContainerInterface|MockObject $container;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->factory = new MiddlewareFactory($this->container);
    }

    public function testMakeMiddlewareWithExistingMiddleware(): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $result = $this->factory->makeMiddleware($middleware);

        $this->assertSame($middleware, $result);
    }

    public function testMakeMiddlewareWithRequestHandler(): void
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $result = $this->factory->makeMiddleware($handler);

        $this->assertInstanceOf(RequestHandlerAdapter::class, $result);

        // Test adapter functionality
        $request = $this->createMock(ServerRequestInterface::class);
        $nextHandler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $requestWithAttribute = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())
            ->method('withAttribute')
            ->with(RequestHandlerAdapter::FALLBACK_HANDLER_ATTRIBUTES_KEY, $nextHandler)
            ->willReturn($requestWithAttribute);

        $handler->expects($this->once())
            ->method('handle')
            ->with($requestWithAttribute)
            ->willReturn($response);

        $actualResponse = $result->process($request, $nextHandler);
        $this->assertSame($response, $actualResponse);
    }

    public function testMakeMiddlewareWithSuccessfulStrategy(): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $strategy = $this->createMock(StrategyInterface::class);

        $strategy->expects($this->once())
            ->method('makeMiddleware')
            ->with('test-middleware')
            ->willReturn($middleware);

        $this->factory->addStrategy($strategy);
        $result = $this->factory->makeMiddleware('test-middleware');

        $this->assertSame($middleware, $result);
    }

    public function testMakeMiddlewareWithMultipleStrategies(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);

        // First strategy handles 'type-a', returns null for everything else
        $strategy1 = $this->createMock(StrategyInterface::class);
        $strategy1->expects($this->exactly(2))
            ->method('makeMiddleware')
            ->willReturnCallback(function($middleware) use ($middleware1) {
                return $middleware === 'type-a' ? $middleware1 : null;
            });

        // Second strategy handles 'type-b'
        $strategy2 = $this->createMock(StrategyInterface::class);
        $strategy2->expects($this->once())
            ->method('makeMiddleware')
            ->with('type-b')
            ->willReturn($middleware2);

        $this->factory->addStrategy($strategy1);
        $this->factory->addStrategy($strategy2);

        $result1 = $this->factory->makeMiddleware('type-a');
        $result2 = $this->factory->makeMiddleware('type-b');

        $this->assertSame($middleware1, $result1);
        $this->assertSame($middleware2, $result2);
    }

    public function testMakeMiddlewareWithStrategyPriority(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);

        $strategy1 = $this->createMock(StrategyInterface::class);
        $strategy1->expects($this->never())
            ->method('makeMiddleware');

        $strategy2 = $this->createMock(StrategyInterface::class);
        $strategy2->expects($this->once())
            ->method('makeMiddleware')
            ->with('test')
            ->willReturn($middleware2);

        $this->factory->addStrategy($strategy1);
        $this->factory->addStrategy($strategy2, true); // Prepend

        $result = $this->factory->makeMiddleware('test');

        $this->assertSame($middleware2, $result);
    }

    public function testMakeMiddlewareWithUnresolvableInput(): void
    {
        $strategy = $this->createMock(StrategyInterface::class);
        $strategy->expects($this->once())
            ->method('makeMiddleware')
            ->with('unresolvable')
            ->willReturn(null);

        $this->factory->addStrategy($strategy);

        $this->expectException(MiddlewareResolutionException::class);
        $this->expectExceptionMessage('Cannot resolve middleware');

        $this->factory->makeMiddleware('unresolvable');
    }

    public function testMakeMiddlewareWithNoStrategies(): void
    {
        $this->expectException(MiddlewareResolutionException::class);
        $this->expectExceptionMessage('Cannot resolve middleware');

        $this->factory->makeMiddleware('something');
    }

    public function testAddStrategyWithContainerAware(): void
    {
        $strategy = $this->createMock(TestContainerAwareStrategy::class);

        $strategy->expects($this->once())
            ->method('setContainer')
            ->with($this->container);

        $this->factory->addStrategy($strategy);
    }

    public function testAddStrategyWithMiddlewareFactoryAware(): void
    {
        $strategy = $this->createMock(TestMiddlewareFactoryAwareStrategy::class);

        $strategy->expects($this->once())
            ->method('setMiddlewareFactory')
            ->with($this->factory);

        $this->factory->addStrategy($strategy);
    }

    public function testStrategyOrderPreservation(): void
    {
        $callOrder = [];

        $strategy1 = $this->createMock(StrategyInterface::class);
        $strategy1->expects($this->once())
            ->method('makeMiddleware')
            ->willReturnCallback(function() use (&$callOrder) {
                $callOrder[] = 'strategy1';
                return null;
            });

        $strategy2 = $this->createMock(StrategyInterface::class);
        $strategy2->expects($this->once())
            ->method('makeMiddleware')
            ->willReturnCallback(function() use (&$callOrder) {
                $callOrder[] = 'strategy2';
                return null;
            });

        $strategy3 = $this->createMock(StrategyInterface::class);
        $strategy3->expects($this->once())
            ->method('makeMiddleware')
            ->willReturnCallback(function() use (&$callOrder) {
                $callOrder[] = 'strategy3';
                return null;
            });

        $this->factory->addStrategy($strategy1);
        $this->factory->addStrategy($strategy2);
        $this->factory->addStrategy($strategy3, true); // Prepend

        try {
            $this->factory->makeMiddleware('test');
        } catch (MiddlewareResolutionException) {
            // Ignore exception, we only care about call order
        }

        $this->assertEquals(['strategy3', 'strategy1', 'strategy2'], $callOrder);
    }

    public function testStrategyExceptionHandling(): void
    {
        $strategy = $this->createMock(StrategyInterface::class);
        $strategy->expects($this->once())
            ->method('makeMiddleware')
            ->willThrowException(new RuntimeException('Strategy error'));

        $this->factory->addStrategy($strategy);

        $this->expectException(MiddlewareResolutionException::class);
        $this->expectExceptionMessage('Strategy error');

        $this->factory->makeMiddleware('test');
    }

    public function testBuiltInTypesProcessedAfterStrategies(): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);

        $strategy = $this->createMock(StrategyInterface::class);
        $strategy->expects($this->once())
            ->method('makeMiddleware')
            ->with($middleware)
            ->willReturn(null);

        $this->factory->addStrategy($strategy);

        $result = $this->factory->makeMiddleware($middleware);

        $this->assertSame($middleware, $result);
    }

    public function testStrategyExceptionStopsProcessing(): void
    {
        $strategy1 = $this->createMock(StrategyInterface::class);
        $strategy1->expects($this->once())
            ->method('makeMiddleware')
            ->willThrowException(new RuntimeException('First strategy error'));

        $strategy2 = $this->createMock(StrategyInterface::class);
        $strategy2->expects($this->never())
            ->method('makeMiddleware');

        $this->factory->addStrategy($strategy1);
        $this->factory->addStrategy($strategy2);

        $this->expectException(MiddlewareResolutionException::class);
        $this->expectExceptionMessage('First strategy error');

        $this->factory->makeMiddleware('test');
    }

    public function testStrategyExceptionWrapping(): void
    {
        $originalException = new \InvalidArgumentException('Original error');

        $strategy = $this->createMock(StrategyInterface::class);
        $strategy->expects($this->once())
            ->method('makeMiddleware')
            ->willThrowException($originalException);

        $this->factory->addStrategy($strategy);

        try {
            $this->factory->makeMiddleware('test');
            $this->fail('Expected exception was not thrown');
        } catch (MiddlewareResolutionException $e) {
            $this->assertSame($originalException, $e->getPrevious());
            $this->assertStringContainsString('Original error', $e->getMessage());
        }
    }

    public function testMakeMiddlewareWithStrategyReturningNull(): void
    {
        $strategy1 = $this->createMock(StrategyInterface::class);
        $strategy1->expects($this->exactly(2))
            ->method('makeMiddleware')
            ->willReturn(null);

        $middleware = $this->createMock(MiddlewareInterface::class);
        $strategy2 = $this->createMock(StrategyInterface::class);
        $strategy2->expects($this->exactly(2))
            ->method('makeMiddleware')
            ->willReturnCallback(function($definition) use ($middleware) {
                return $definition === 'handled' ? $middleware : null;
            });

        $this->factory->addStrategy($strategy1);
        $this->factory->addStrategy($strategy2);

        $result = $this->factory->makeMiddleware('handled');
        $this->assertSame($middleware, $result);

        // Test unhandled input throws exception
        $this->expectException(MiddlewareResolutionException::class);
        $this->factory->makeMiddleware('not-handled');
    }

    public function testCreateFromContainerMethod(): void
    {
        $this->assertTrue(method_exists(MiddlewareFactory::class, 'createFromContainer'));
    }

    public function testMiddlewareFactoryInterface(): void
    {
        $this->assertInstanceOf(\Bermuda\MiddlewareFactory\MiddlewareFactoryInterface::class, $this->factory);
        $this->assertTrue(method_exists($this->factory, 'makeMiddleware'));
    }

    public function testExceptionImplementsCorrectInterface(): void
    {
        $strategy = $this->createMock(StrategyInterface::class);
        $strategy->expects($this->once())
            ->method('makeMiddleware')
            ->willThrowException(new RuntimeException('Test error'));

        $this->factory->addStrategy($strategy);

        try {
            $this->factory->makeMiddleware('test');
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertInstanceOf(MiddlewareResolutionExceptionInterface::class, $e);
            $this->assertInstanceOf(MiddlewareResolutionException::class, $e);
        }
    }

    public function testStrategyProcessingStopsOnFirstSuccess(): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);

        $strategy1 = $this->createMock(StrategyInterface::class);
        $strategy1->expects($this->once())
            ->method('makeMiddleware')
            ->with('test')
            ->willReturn($middleware);

        $strategy2 = $this->createMock(StrategyInterface::class);
        $strategy2->expects($this->never())
            ->method('makeMiddleware');

        $this->factory->addStrategy($strategy1);
        $this->factory->addStrategy($strategy2);

        $result = $this->factory->makeMiddleware('test');

        $this->assertSame($middleware, $result);
    }
}

/**
 * Mock interfaces for testing dependency injection
 */
interface TestContainerAwareStrategy extends StrategyInterface, ContainerAwareInterface
{
}

interface TestMiddlewareFactoryAwareStrategy extends StrategyInterface, MiddlewareFactoryAwareInterface
{
}