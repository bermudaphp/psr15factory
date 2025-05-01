<?php

namespace Bermuda\MiddlewareFactory\Strategy;

use Bermuda\MiddlewareFactory\MiddlewareFactoryInterface;
use Bermuda\Pipeline\PipelineFactory;
use Bermuda\Pipeline\PipelineFactoryInterface;
use Bermuda\Pipeline\PipelineInterface;
use Psr\Container\ContainerInterface;

/**
 * MiddlewarePipelineStrategy creates a middleware pipeline from an iterable collection of middleware.
 *
 * This strategy checks if the provided middleware is iterable. If it is, the strategy delegates the creation
 * of a PipelineInterface instance to a PipelineFactoryInterface. This allows multiple pieces of middleware to
 * be combined into a single pipeline that can process server requests sequentially.
 *
 * If the middleware is not iterable, the strategy returns null, indicating that the input is not valid for
 * pipeline creation.
 */
final class MiddlewarePipelineStrategy implements StrategyInterface
{
    /**
     * Constructor for MiddlewarePipelineStrategy.
     *
     * @param PipelineFactoryInterface $pipelineFactory The factory used to create middleware pipelines.
     *                                                    Defaults to a new PipelineFactory instance if not provided.
     */
    public function __construct(
        private readonly PipelineFactoryInterface $pipelineFactory = new PipelineFactory
    ) {
    }

    /**
     * Creates a middleware pipeline if the provided middleware is iterable.
     *
     * @param mixed $middleware The middleware definition, expected to be an iterable collection.
     *
     * @return PipelineInterface|null Returns a PipelineInterface instance if $middleware is iterable,
     *                                otherwise returns null.
     */
    public function createMiddleware(mixed $middleware): ?PipelineInterface
    {
        return is_iterable($middleware)
            ? $this->pipelineFactory->createMiddlewarePipeline($middleware)
            : null;
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
        return new self($container->get(PipelineFactoryInterface::class));
    }
}
