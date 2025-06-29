<?php

declare(strict_types=1);

namespace Bermuda\MiddlewareFactory\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Container\ContainerInterface;
use Bermuda\DI\CallableExecutorInterface;
use Bermuda\MiddlewareFactory\Adapter\CallableAdapter;
use Bermuda\MiddlewareFactory\Strategy\CallableStrategy;

/**
 * Tests for CallableStrategy class
 */
class CallableStrategyTest extends TestCase
{
    private CallableStrategy $strategy;
    private CallableExecutorInterface|MockObject $executor;
    private ResponseFactoryInterface|MockObject $responseFactory;

    protected function setUp(): void
    {
        $this->executor = $this->createMock(CallableExecutorInterface::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->strategy = new CallableStrategy($this->executor, $this->responseFactory);
    }

    public function testMakeMiddlewareWithNonResolvableCallable(): void
    {
        $nonCallable = 'non-existent-function';

        $this->executor->expects($this->once())
            ->method('resolve')
            ->with($nonCallable)
            ->willReturn(null);

        $result = $this->strategy->makeMiddleware($nonCallable);
        $this->assertNull($result);
    }

    public function testMakeMiddlewareWithStandardCallable(): void
    {
        $callable = function(string $someParam): ResponseInterface {
            return $this->createMock(ResponseInterface::class);
        };

        $this->executor->expects($this->once())
            ->method('resolve')
            ->with($callable)
            ->willReturn($callable);

        $result = $this->strategy->makeMiddleware($callable);

        $this->assertInstanceOf(CallableAdapter::class, $result);
        $reflection = new \ReflectionClass($result);
        $this->assertEquals(CallableAdapter::class, $reflection->getName());
    }

    public function testMakeMiddlewareWithSinglePassSignature(): void
    {
        $callable = function(ServerRequestInterface $request, callable $next): ResponseInterface {
            return $next($request);
        };

        $this->executor->expects($this->once())
            ->method('resolve')
            ->with($callable)
            ->willReturn($callable);

        $result = $this->strategy->makeMiddleware($callable);

        $this->assertInstanceOf(CallableAdapter::class, $result);
        $reflection = new \ReflectionClass($result);
        $this->assertTrue($reflection->isAnonymous());
    }

    public function testMakeMiddlewareWithDoublePassSignature(): void
    {
        $callable = function(ServerRequestInterface $request, ResponseInterface $response, callable $next): ResponseInterface {
            return $response;
        };

        $this->executor->expects($this->once())
            ->method('resolve')
            ->with($callable)
            ->willReturn($callable);

        $result = $this->strategy->makeMiddleware($callable);

        $this->assertInstanceOf(CallableAdapter::class, $result);
        $reflection = new \ReflectionClass($result);
        $this->assertTrue($reflection->isAnonymous());
    }

    public function testMakeMiddlewareWithInvalidFirstParameter(): void
    {
        $callable = function(string $notRequest, callable $next): ResponseInterface {
            return $this->createMock(ResponseInterface::class);
        };

        $this->executor->expects($this->once())
            ->method('resolve')
            ->with($callable)
            ->willReturn($callable);

        $result = $this->strategy->makeMiddleware($callable);

        $this->assertInstanceOf(CallableAdapter::class, $result);
        $reflection = new \ReflectionClass($result);
        $this->assertEquals(CallableAdapter::class, $reflection->getName());
    }

    public function testMakeMiddlewareWithTwoParametersButNotCallable(): void
    {
        $callable = function(ServerRequestInterface $request, string $notCallable): ResponseInterface {
            return $this->createMock(ResponseInterface::class);
        };

        $this->executor->expects($this->once())
            ->method('resolve')
            ->with($callable)
            ->willReturn($callable);

        $result = $this->strategy->makeMiddleware($callable);

        $this->assertInstanceOf(CallableAdapter::class, $result);
        $reflection = new \ReflectionClass($result);
        $this->assertEquals(CallableAdapter::class, $reflection->getName());
    }

    public function testMakeMiddlewareWithThreeParametersInvalidSecond(): void
    {
        $callable = function(ServerRequestInterface $request, string $notResponse, callable $next): ResponseInterface {
            return $this->createMock(ResponseInterface::class);
        };

        $this->executor->expects($this->once())
            ->method('resolve')
            ->with($callable)
            ->willReturn($callable);

        $result = $this->strategy->makeMiddleware($callable);

        $this->assertInstanceOf(CallableAdapter::class, $result);
        $reflection = new \ReflectionClass($result);
        $this->assertEquals(CallableAdapter::class, $reflection->getName());
    }

    public function testMakeMiddlewareWithCallableArray(): void
    {
        $callableArray = [self::class, 'setUp'];

        // Executor resolves array to a closure
        $resolvedCallable = function() { return 'test'; };

        $this->executor->expects($this->once())
            ->method('resolve')
            ->with($callableArray)
            ->willReturn($resolvedCallable);

        $result = $this->strategy->makeMiddleware($callableArray);
        $this->assertInstanceOf(CallableAdapter::class, $result);
    }

    public function testMakeMiddlewareWithExecutorException(): void
    {
        $callable = fn() => 'test';

        $this->executor->expects($this->once())
            ->method('resolve')
            ->with($callable)
            ->willThrowException(new \RuntimeException('Resolution failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Resolution failed');

        $this->strategy->makeMiddleware($callable);
    }

    public function testCreateFromContainer(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $executor = $this->createMock(CallableExecutorInterface::class);
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);

        $container->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function($serviceId) use ($executor, $responseFactory) {
                return match($serviceId) {
                    CallableExecutorInterface::class => $executor,
                    ResponseFactoryInterface::class => $responseFactory,
                    default => throw new \Exception("Unexpected service: $serviceId")
                };
            });

        $strategy = CallableStrategy::createFromContainer($container);
        $this->assertInstanceOf(CallableStrategy::class, $strategy);
    }

    public function testMakeMiddlewareWithSignatureDetectionAccuracy(): void
    {
        $singlePass = function(ServerRequestInterface $request, callable $next): ResponseInterface {
            return $this->createMock(ResponseInterface::class);
        };

        $doublePass = function(ServerRequestInterface $request, ResponseInterface $response, callable $next): ResponseInterface {
            return $response;
        };

        $standard = function(ServerRequestInterface $request): ResponseInterface {
            return $this->createMock(ResponseInterface::class);
        };

        // Mock each call separately to avoid type issues
        $this->executor->expects($this->exactly(3))
            ->method('resolve')
            ->willReturnCallback(function($callable) {
                // Return the same callable - closures are valid callables
                return $callable;
            });

        $result1 = $this->strategy->makeMiddleware($singlePass);
        $this->assertInstanceOf(CallableAdapter::class, $result1);
        $reflection1 = new \ReflectionClass($result1);
        $this->assertTrue($reflection1->isAnonymous(), 'Single-pass should create anonymous class');

        $result2 = $this->strategy->makeMiddleware($doublePass);
        $this->assertInstanceOf(CallableAdapter::class, $result2);
        $reflection2 = new \ReflectionClass($result2);
        $this->assertTrue($reflection2->isAnonymous(), 'Double-pass should create anonymous class');

        $result3 = $this->strategy->makeMiddleware($standard);
        $this->assertInstanceOf(CallableAdapter::class, $result3);
        $reflection3 = new \ReflectionClass($result3);
        $this->assertFalse($reflection3->isAnonymous(), 'Standard should create regular CallableAdapter');
        $this->assertEquals(CallableAdapter::class, $reflection3->getName());
    }

    public function testSignatureDetectionWithNoTypeHints(): void
    {
        $callable = function($request, $next) {
            return $this->createMock(ResponseInterface::class);
        };

        $this->executor->expects($this->once())
            ->method('resolve')
            ->with($callable)
            ->willReturn($callable);

        $result = $this->strategy->makeMiddleware($callable);

        $this->assertInstanceOf(CallableAdapter::class, $result);
        $reflection = new \ReflectionClass($result);
        $this->assertEquals(CallableAdapter::class, $reflection->getName());
    }

    public function testMakeMiddlewareWithUnionTypes(): void
    {
        $callable = function(ServerRequestInterface $request, callable|null $next = null): ResponseInterface {
            return $this->createMock(ResponseInterface::class);
        };

        $this->executor->expects($this->once())
            ->method('resolve')
            ->with($callable)
            ->willReturn($callable);

        $result = $this->strategy->makeMiddleware($callable);

        $this->assertInstanceOf(CallableAdapter::class, $result);
        $reflection = new \ReflectionClass($result);
        $this->assertTrue($reflection->isAnonymous(), 'Should detect callable in union type');
    }

    public function testMakeMiddlewareWithClosureType(): void
    {
        $callable = function(ServerRequestInterface $request, \Closure $next): ResponseInterface {
            return $this->createMock(ResponseInterface::class);
        };

        $this->executor->expects($this->once())
            ->method('resolve')
            ->with($callable)
            ->willReturn($callable);

        $result = $this->strategy->makeMiddleware($callable);

        // \Closure не должен распознаваться как callable type
        $this->assertInstanceOf(CallableAdapter::class, $result);
        $reflection = new \ReflectionClass($result);
        $this->assertEquals(CallableAdapter::class, $reflection->getName());
    }

    public function testMakeMiddlewareWithStringFunction(): void
    {
        $functionName = 'strlen';

        $this->executor->expects($this->once())
            ->method('resolve')
            ->with($functionName)
            ->willReturn($functionName); // String function names are valid callables

        $result = $this->strategy->makeMiddleware($functionName);
        $this->assertInstanceOf(CallableAdapter::class, $result);
    }

    public function testMakeMiddlewareWithInvokableObject(): void
    {
        $invokable = new class {
            public function __invoke(ServerRequestInterface $request): ResponseInterface {
                return $this->createMock(ResponseInterface::class);
            }
        };

        $this->executor->expects($this->once())
            ->method('resolve')
            ->with($invokable)
            ->willReturn($invokable);

        $result = $this->strategy->makeMiddleware($invokable);
        $this->assertInstanceOf(CallableAdapter::class, $result);
    }

    public function testMakeMiddlewareWithStaticMethodArray(): void
    {
        $callableArray = [\DateTime::class, 'createFromFormat'];

        // Executor resolves to a closure representing the static method
        $resolvedCallable = function() { return 'static method result'; };

        $this->executor->expects($this->once())
            ->method('resolve')
            ->with($callableArray)
            ->willReturn($resolvedCallable);

        $result = $this->strategy->makeMiddleware($callableArray);
        $this->assertInstanceOf(CallableAdapter::class, $result);
    }
}