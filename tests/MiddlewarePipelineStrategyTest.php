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
use Bermuda\MiddlewareFactory\Strategy\MiddlewarePipelineStrategy;
use Bermuda\MiddlewareFactory\Strategy\StrategyInterface;
use Bermuda\MiddlewareFactory\MiddlewareFactoryAwareInterface;
use Bermuda\MiddlewareFactory\MiddlewareGroup;
use Bermuda\MiddlewareFactory\MiddlewareFactoryInterface;
use Bermuda\Pipeline\PipelineInterface;
use RuntimeException;

/**
 * Tests for MiddlewarePipelineStrategy class
 */
class MiddlewarePipelineStrategyTest extends TestCase
{
    private MiddlewarePipelineStrategy $strategy;
    private MiddlewareFactoryInterface|MockObject $middlewareFactory;

    protected function setUp(): void
    {
        $this->middlewareFactory = $this->createMock(MiddlewareFactoryInterface::class);
        $this->strategy = new MiddlewarePipelineStrategy($this->middlewareFactory);
    }

    public function testMakeMiddlewareWithPipeline(): void
    {
        $pipeline = $this->createMock(PipelineInterface::class);
        $result = $this->strategy->makeMiddleware($pipeline);

        $this->assertSame($pipeline, $result);
    }

    public function testMakeMiddlewareWithNonPipelineAndNonGroup(): void
    {
        $nonPipelineInputs = [
            'string',
            123,
            [],
            new \stdClass(),
            $this->createMock(MiddlewareInterface::class),
            $this->createMock(RequestHandlerInterface::class),
            null,
            true,
            false
        ];

        foreach ($nonPipelineInputs as $input) {
            $result = $this->strategy->makeMiddleware($input);
            $this->assertNull($result, 'Strategy should return null for non-pipeline/non-group input: ' . gettype($input));
        }
    }

    public function testMakeMiddlewareWithEmptyMiddlewareGroup(): void
    {
        $emptyGroup = new MiddlewareGroup([]);

        $result = $this->strategy->makeMiddleware($emptyGroup);

        $this->assertInstanceOf(PipelineInterface::class, $result);
    }

    public function testMakeMiddlewareWithMiddlewareGroup(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);

        $group = new MiddlewareGroup(['middleware1', 'middleware2']);

        $this->middlewareFactory->expects($this->exactly(2))
            ->method('makeMiddleware')
            ->willReturnCallback(function($definition) use ($middleware1, $middleware2) {
                return match($definition) {
                    'middleware1' => $middleware1,
                    'middleware2' => $middleware2,
                    default => throw new \Exception("Unexpected middleware definition: $definition")
                };
            });

        $result = $this->strategy->makeMiddleware($group);

        $this->assertInstanceOf(PipelineInterface::class, $result);
    }

    public function testMakeMiddlewareWithComplexMiddlewareGroup(): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $handlerAsMiddleware = $this->createMock(MiddlewareInterface::class);
        $callable = fn() => 'test';

        $group = new MiddlewareGroup([
            'TestMiddleware',
            'TestHandler',
            $callable
        ]);

        $this->middlewareFactory->expects($this->exactly(3))
            ->method('makeMiddleware')
            ->willReturnCallback(function($definition) use ($middleware, $handlerAsMiddleware, $callable) {
                return match($definition) {
                    'TestMiddleware' => $middleware,
                    'TestHandler' => $handlerAsMiddleware, // Handler converted to middleware
                    $callable => $middleware, // Callable resolves to middleware
                    default => throw new \Exception("Unexpected middleware definition")
                };
            });

        $result = $this->strategy->makeMiddleware($group);

        $this->assertInstanceOf(PipelineInterface::class, $result);
    }

    public function testMakeMiddlewareWithNestedMiddlewareGroups(): void
    {
        $innerGroup = new MiddlewareGroup(['inner-middleware']);
        $outerGroup = new MiddlewareGroup(['outer-middleware', $innerGroup]);

        $outerMiddleware = $this->createMock(MiddlewareInterface::class);
        $innerPipeline = $this->createMock(PipelineInterface::class);

        $this->middlewareFactory->expects($this->exactly(2))
            ->method('makeMiddleware')
            ->willReturnCallback(function($definition) use ($outerMiddleware, $innerGroup, $innerPipeline) {
                return match($definition) {
                    'outer-middleware' => $outerMiddleware,
                    $innerGroup => $innerPipeline,
                    default => throw new \Exception("Unexpected middleware definition")
                };
            });

        $result = $this->strategy->makeMiddleware($outerGroup);

        $this->assertInstanceOf(PipelineInterface::class, $result);
    }

    public function testMakeMiddlewareWithoutMiddlewareFactory(): void
    {
        $strategyWithoutFactory = new MiddlewarePipelineStrategy();
        $group = new MiddlewareGroup(['test-middleware']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MiddlewareFactory is required to convert MiddlewareGroup to Pipeline');

        $strategyWithoutFactory->makeMiddleware($group);
    }

    public function testSetMiddlewareFactory(): void
    {
        $strategy = new MiddlewarePipelineStrategy();
        $factory = $this->createMock(MiddlewareFactoryInterface::class);

        $strategy->setMiddlewareFactory($factory);

        // Test that factory is set by successfully converting an empty group
        $group = new MiddlewareGroup([]);
        $result = $strategy->makeMiddleware($group);

        $this->assertInstanceOf(PipelineInterface::class, $result);
    }

    public function testMiddlewareFactoryAwareInterface(): void
    {
        $this->assertInstanceOf(
            MiddlewareFactoryAwareInterface::class,
            $this->strategy
        );
        $this->assertTrue(method_exists($this->strategy, 'setMiddlewareFactory'));
    }

    public function testConvertGroupToPipelineWithMiddlewareFactoryError(): void
    {
        $group = new MiddlewareGroup(['failing-middleware']);

        $this->middlewareFactory->expects($this->once())
            ->method('makeMiddleware')
            ->with('failing-middleware')
            ->willThrowException(new \Exception('Factory error'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Factory error');

        $this->strategy->makeMiddleware($group);
    }

    public function testCreateFromContainer(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $factory = $this->createMock(MiddlewareFactoryInterface::class);

        $container->expects($this->once())
            ->method('get')
            ->with(MiddlewareFactoryInterface::class)
            ->willReturn($factory);

        $strategy = MiddlewarePipelineStrategy::createFromContainer($container);
        $this->assertInstanceOf(MiddlewarePipelineStrategy::class, $strategy);
    }

    public function testStrategyDoesNotModifyInput(): void
    {
        $pipeline = $this->createMock(PipelineInterface::class);
        $result = $this->strategy->makeMiddleware($pipeline);

        $this->assertSame($pipeline, $result);
    }

    public function testStrategyWithMultiplePipelines(): void
    {
        $pipeline1 = $this->createMock(PipelineInterface::class);
        $pipeline2 = $this->createMock(PipelineInterface::class);

        $result1 = $this->strategy->makeMiddleware($pipeline1);
        $result2 = $this->strategy->makeMiddleware($pipeline2);

        $this->assertSame($pipeline1, $result1);
        $this->assertSame($pipeline2, $result2);
        $this->assertNotSame($result1, $result2);
    }

    public function testStrategyInterfaceContract(): void
    {
        $this->assertInstanceOf(StrategyInterface::class, $this->strategy);
        $this->assertTrue(method_exists($this->strategy, 'makeMiddleware'));
    }

    public function testMiddlewareGroupWithDifferentDefinitionTypes(): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $callable = fn() => 'response';
        $className = 'SomeMiddlewareClass';

        $group = new MiddlewareGroup([
            $middleware,
            $handler,
            $callable,
            $className,
        ]);

        $resolvedCallable = $this->createMock(MiddlewareInterface::class);
        $resolvedClass = $this->createMock(MiddlewareInterface::class);
        $resolvedHandler = $this->createMock(MiddlewareInterface::class);

        $this->middlewareFactory->expects($this->exactly(4))
            ->method('makeMiddleware')
            ->willReturnCallback(function($definition) use (
                $middleware, $handler, $callable, $className,
                $resolvedCallable, $resolvedClass, $resolvedHandler
            ) {
                return match($definition) {
                    $middleware => $middleware,
                    $handler => $resolvedHandler, // Handler converted to middleware
                    $callable => $resolvedCallable,
                    $className => $resolvedClass,
                    default => throw new \Exception("Unexpected definition")
                };
            });

        $result = $this->strategy->makeMiddleware($group);

        $this->assertInstanceOf(PipelineInterface::class, $result);
    }

    public function testMiddlewareGroupConversionOrder(): void
    {
        $group = new MiddlewareGroup(['first', 'second', 'third']);

        $callOrder = [];

        $this->middlewareFactory->expects($this->exactly(3))
            ->method('makeMiddleware')
            ->willReturnCallback(function($definition) use (&$callOrder) {
                $callOrder[] = $definition;
                return $this->createMock(MiddlewareInterface::class);
            });

        $this->strategy->makeMiddleware($group);

        $this->assertEquals(['first', 'second', 'third'], $callOrder);
    }

    public function testMiddlewareGroupIsCountable(): void
    {
        $group = new MiddlewareGroup(['m1', 'm2', 'm3']);

        $this->assertEquals(3, $group->count());
        $this->assertEquals(3, count($group)); // Test Countable interface
    }

    public function testMiddlewareGroupIsIterable(): void
    {
        $middlewares = ['m1', 'm2', 'm3'];
        $group = new MiddlewareGroup($middlewares);

        $result = [];
        foreach ($group as $middleware) {
            $result[] = $middleware;
        }

        $this->assertEquals($middlewares, $result);
    }

    public function testMiddlewareGroupImmutability(): void
    {
        $original = new MiddlewareGroup(['m1']);

        $withAdded = $original->add('m2');
        $withMany = $original->addMany(['m3', 'm4']);

        // Original should not be modified
        $this->assertEquals(1, $original->count());
        $this->assertEquals(['m1'], iterator_to_array($original));

        // New instances should have correct content
        $this->assertEquals(2, $withAdded->count());
        $this->assertEquals(['m1', 'm2'], iterator_to_array($withAdded));

        $this->assertEquals(3, $withMany->count());
        $this->assertEquals(['m1', 'm3', 'm4'], iterator_to_array($withMany));
    }

    public function testMiddlewareGroupPrepend(): void
    {
        $group = new MiddlewareGroup(['m2', 'm3']);

        $withPrepended = $group->add('m1', true);
        $withManyPrepended = $group->addMany(['m0', 'm0.5'], true);

        $this->assertEquals(['m1', 'm2', 'm3'], iterator_to_array($withPrepended));
        $this->assertEquals(['m0', 'm0.5', 'm2', 'm3'], iterator_to_array($withManyPrepended));
    }

    public function testMiddlewareGroupEmptyChecks(): void
    {
        $empty = new MiddlewareGroup([]);
        $nonEmpty = new MiddlewareGroup(['m1']);

        $this->assertTrue($empty->isEmpty());
        $this->assertFalse($nonEmpty->isEmpty());

        $this->assertEquals(0, $empty->count());
        $this->assertEquals(1, $nonEmpty->count());
    }
}