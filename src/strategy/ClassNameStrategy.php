<?php

namespace Bermuda\MiddlewareFactory\Strategy;

use Bermuda\MiddlewareFactory\Adapter\RequestHandlerAdapter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Strategy for resolving middleware from fully-qualified class names.
 *
 * This strategy resolves middleware definitions provided as string class names
 * by retrieving instances from a PSR-11 container. It supports both:
 * - Classes implementing MiddlewareInterface (returned directly)
 * - Classes implementing RequestHandlerInterface (wrapped in RequestHandlerAdapter)
 *
 * The strategy validates that the class exists and implements the required interface
 * before attempting container resolution to avoid unnecessary container calls.
 */
final class ClassNameStrategy implements StrategyInterface
{
    /**
     * Creates a new class name strategy with the specified container.
     *
     * @param ContainerInterface $container PSR-11 container for resolving class instances
     */
    public function __construct(
        private readonly ContainerInterface $container
    ) {
    }

    /**
     * Resolves middleware from a class name string.
     *
     * Resolution process:
     * 1. Validates input is a string (class name)
     * 2. Attempts to resolve as MiddlewareInterface implementation
     * 3. Falls back to RequestHandlerInterface implementation (wrapped in adapter)
     * 4. Returns null if neither interface is implemented or class not found
     *
     * @param mixed $middleware The middleware definition (expected to be a class name string)
     * @return MiddlewareInterface|null Resolved middleware instance or null if resolution fails
     * @throws ContainerExceptionInterface If container access fails
     */
    public function makeMiddleware(mixed $middleware): ?MiddlewareInterface
    {
        return match (true) {
            !is_string($middleware) => null,
            ($obj = $this->get($middleware, MiddlewareInterface::class)) !== null => $obj,
            ($obj = $this->get($middleware, RequestHandlerInterface::class)) !== null => new RequestHandlerAdapter($obj),
            default => null
        };
    }

    /**
     * Retrieves an instance from the container if the class implements the specified interface.
     *
     * This helper method performs interface validation before container access to ensure
     * efficient resolution and avoid unnecessary container calls for incompatible classes.
     *
     * @param string $middleware The fully-qualified class name
     * @param string $interface The required interface or parent class
     * @return MiddlewareInterface|RequestHandlerInterface|null Container instance if valid, null otherwise
     * @throws ContainerExceptionInterface If container access fails
     */
    private function get(string $middleware, string $interface): null|MiddlewareInterface|RequestHandlerInterface
    {
        // Validate class implements required interface and exists in container
        if (is_subclass_of($middleware, $interface) && $this->container->has($middleware)) {
            return $this->container->get($middleware);
        }

        return null;
    }

    /**
     * Factory method to create ClassNameStrategy from a container.
     *
     * @param ContainerInterface $container PSR-11 container for dependency resolution
     * @return ClassNameStrategy New strategy instance
     */
    public static function createFromContainer(ContainerInterface $container): ClassNameStrategy
    {
        return new self($container);
    }
}