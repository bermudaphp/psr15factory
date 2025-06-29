<?php

namespace Bermuda\MiddlewareFactory\Strategy;

use Bermuda\Http\Middleware\EmptyPipelineHandler;
use Psr\Http\Server\MiddlewareInterface;
use Bermuda\MiddlewareFactory\MiddlewareFactoryAwareInterface;
use Bermuda\MiddlewareFactory\MiddlewareFactoryInterface;
use Bermuda\MiddlewareFactory\MiddlewareGroup;
use Bermuda\Http\Middleware\Pipeline;
use Bermuda\Http\Middleware\PipelineFactory;
use Bermuda\Http\Middleware\PipelineFactoryInterface;
use Bermuda\Http\Middleware\PipelineInterface;
use Psr\Container\ContainerInterface;

/**
 * Strategy for resolving Pipeline and MiddlewareGroup into middleware.
 *
 * Supports:
 * - PipelineInterface (ready pipelines with MiddlewareInterface instances)
 * - MiddlewareGroup (groups of middleware definitions for conversion)
 */
final class MiddlewarePipelineStrategy implements StrategyInterface, MiddlewareFactoryAwareInterface
{
    public function __construct(
        private ?MiddlewareFactoryInterface $middlewareFactory = null
    ) {
    }

    /**
     * Sets the middleware factory instance.
     *
     * @param MiddlewareFactoryInterface $middlewareFactory The middleware factory
     * @return void
     */
    public function setMiddlewareFactory(MiddlewareFactoryInterface $middlewareFactory): void
    {
        $this->middlewareFactory = $middlewareFactory;
    }

    /**
     * Creates a middleware pipeline if the provided middleware is iterable.
     *
     * @param mixed $middleware The middleware definition, expected to be an iterable collection.
     *
     * @return PipelineInterface|null Returns a PipelineInterface instance if $middleware is iterable,
     *                                otherwise returns null.
     */
    public function makeMiddleware(mixed $middleware): ?MiddlewareInterface
    {
        return match (true) {
            $middleware instanceof PipelineInterface => $middleware,
            $middleware instanceof MiddlewareGroup => $this->convertGroupToPipeline($middleware),
            default => null
        };
    }

    /**
     * Converts MiddlewareGroup to Pipeline with resolved middleware instances.
     *
     * @param MiddlewareGroup $group Group to convert
     * @return PipelineInterface Ready pipeline with middleware instances
     * @throws \RuntimeException If middleware factory is not set
     */
    private function convertGroupToPipeline(MiddlewareGroup $group): PipelineInterface
    {
        if ($this->middlewareFactory === null) {
            throw new \RuntimeException(
                'MiddlewareFactory is required to convert MiddlewareGroup to Pipeline. ' .
                'Ensure the strategy is properly registered with MiddlewareFactory.'
            );
        }

        $resolvedMiddlewares = [];

        foreach ($group->middlewares as $definition) {
            $resolvedMiddlewares[] = $this->middlewareFactory->makeMiddleware($definition);
        }

        return new Pipeline($resolvedMiddlewares, new EmptyPipelineHandler());
    }

    /**
     * Creates a new instance of MiddlewarePipelineStrategy using a PSR-11 container.
     *
     * This static factory method retrieves the PipelineFactoryInterface dependency from the provided container
     * and uses it to construct a new MiddlewarePipelineStrategy instance. This approach facilitates dependency
     * injection, ensuring that the strategy is decoupled from specific factory implementations.
     *
     * @param ContainerInterface $container The PSR-11 container used to resolve the required PipelineFactoryInterface.
     * @return MiddlewarePipelineStrategy A newly created instance of MiddlewarePipelineStrategy.
     */
    public static function createFromContainer(ContainerInterface $container): MiddlewarePipelineStrategy
    {
        return new self($container->get(MiddlewareFactoryInterface::class));
    }
}
