<?php

namespace Bermuda\MiddlewareFactory;

use Bermuda\ContainerAwareInterface;
use Bermuda\MiddlewareFactory\Strategy\MiddlewarePipelineStrategy;
use Bermuda\MiddlewareFactory\Adapter\RequestHandlerAdapter;
use Bermuda\MiddlewareFactory\Strategy\CallableStrategy;
use Bermuda\MiddlewareFactory\Strategy\ClassNameStrategy;
use Bermuda\MiddlewareFactory\Strategy\StrategyInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function Bermuda\Config\conf;

/**
 * Primary factory for creating PSR-15 middleware instances from various definitions.
 *
 * This factory implements a strategy pattern to resolve different types of middleware
 * definitions (callables, class names, objects, pipelines) into PSR-15 compliant
 * MiddlewareInterface instances. It maintains a collection of resolution strategies
 * that are tried in order until one successfully creates a middleware instance.
 *
 * The factory provides comprehensive error handling with detailed exception messages
 * and backtrace information to help developers debug middleware resolution issues.
 */
final class MiddlewareFactory implements MiddlewareFactoryInterface
{
    /**
     * Collection of registered middleware resolution strategies.
     *
     * @var StrategyInterface[]
     */
    private(set) array $strategies = [];

    /**
     * Creates a new middleware factory with the specified container.
     *
     * @param ContainerInterface $container PSR-11 container for dependency injection
     */
    public function __construct(
        private readonly ContainerInterface $container
    ) {
    }

    /**
     * Resolves a middleware definition to a valid PSR-15 MiddlewareInterface instance.
     *
     * Resolution process:
     * 1. Iterate through registered strategies in order
     * 2. Each strategy attempts to resolve the middleware definition
     * 3. Return the first successful resolution
     * 4. Handle built-in PSR-15 types (MiddlewareInterface, RequestHandlerInterface)
     * 5. Throw detailed exception if resolution fails
     *
     * All exceptions during resolution are enriched with backtrace information
     * and wrapped in MiddlewareResolutionException for consistent error handling.
     *
     * @param mixed $any The middleware definition to resolve
     * @return MiddlewareInterface A valid PSR-15 middleware instance
     * @throws MiddlewareResolutionExceptionInterface If resolution fails
     */
    public function makeMiddleware(mixed $any): MiddlewareInterface
    {
        // Try each registered strategy for custom middleware types
        foreach ($this->strategies as $strategy) {
            try {
                $middleware = $strategy->makeMiddleware($any);
                if ($middleware) return $middleware;
            } catch (\Throwable $e) {
                // Wrap non-resolution exceptions and add backtrace info
                if (!$e instanceof MiddlewareResolutionExceptionInterface) {
                    $e = MiddlewareResolutionException::createFromPrev($any, $e);
                }

                if ($e instanceof BacktraceAwareInterface) {
                    $e->setBacktrace(debug_backtrace()[0]);
                }

                throw $e;
            }
        }

        // Handle built-in PSR-15 middleware types
        if ($any instanceof MiddlewareInterface) {
            return $any;
        }

        if ($any instanceof RequestHandlerInterface) {
            return new RequestHandlerAdapter($any);
        }

        // Create detailed resolution failure exception
        $exception = new MiddlewareResolutionException(
            $any,
            'Cannot resolve middleware: no registered strategy can handle this type of middleware definition'
        );
        $exception->setBacktrace(debug_backtrace()[0]);

        throw $exception;
    }

    /**
     * Registers a new middleware resolution strategy.
     *
     * Strategies are tried in the order they are registered. Use the prepend flag
     * to add high-priority strategies that should be tried first.
     *
     * The factory automatically injects dependencies into strategies:
     * - If strategy implements ContainerAwareInterface, injects the container
     * - If strategy implements MiddlewareFactoryAwareInterface, injects this factory
     *
     * @param StrategyInterface $strategy The resolution strategy to register
     * @param bool $prepend Whether to add the strategy at the beginning (higher priority)
     */
    public function addStrategy(StrategyInterface $strategy, bool $prepend = false): void
    {
        // Inject container if strategy supports it
        if ($strategy instanceof ContainerAwareInterface) {
            $strategy->setContainer($this->container);
        }

        // Inject middleware factory if strategy supports it
        if ($strategy instanceof MiddlewareFactoryAwareInterface) {
            $strategy->setMiddlewareFactory($this);
        }

        // Add strategy with appropriate priority
        if ($prepend) {
            array_unshift($this->strategies, $strategy);
        } else {
            $this->strategies[] = $strategy;
        }
    }

    /**
     * Factory method to create a fully configured MiddlewareFactory from a container.
     *
     * This method creates a factory instance pre-loaded with:
     * 1. Custom strategies from configuration
     * 2. Default built-in strategies (ClassNameStrategy, CallableStrategy, MiddlewarePipelineStrategy)
     *
     * Custom strategies can be configured using the ConfigProvider::CONFIG_KEY_STRATEGIES
     * configuration key and can be either string service names or direct instances.
     *
     * @param ContainerInterface $container PSR-11 container for dependency resolution
     * @return self Fully configured middleware factory instance
     * @throws NotFoundExceptionInterface If a configured strategy service is not found
     * @throws ContainerExceptionInterface If container access fails
     */
    public static function createFromContainer(ContainerInterface $container): self
    {
        $config = conf($container);
        $factory = new self($container);

        // Register custom strategies from configuration
        $customStrategies = $config->get(ConfigProvider::CONFIG_KEY_STRATEGIES, []);
        foreach ($customStrategies as $strategy) {
            if (is_string($strategy)) $strategy = $container->get($strategy);
            $factory->addStrategy($strategy);
        }

        // Register default strategies (order matters - more specific strategies first)
        $factory->addStrategy($container->get(ClassNameStrategy::class));
        $factory->addStrategy($container->get(CallableStrategy::class));
        $factory->addStrategy(new MiddlewarePipelineStrategy()); // No factory injection needed here

        return $factory;
    }
}