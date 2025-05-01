<?php

namespace Bermuda\MiddlewareFactory\Strategy;

use Bermuda\MiddlewareFactory\Adapter\RequestHandlerAdapter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class ClassNameStrategy
 *
 * This strategy is responsible for resolving middleware or request handler definitions provided
 * as fully-qualified class names via a PSR-11 container. When a string (representing a class name)
 * is passed to the strategy, it attempts to retrieve an instance from the container:
 *
 * - If the resolved instance implements MiddlewareInterface, it is returned directly.
 * - If the resolved instance implements RequestHandlerInterface, it is wrapped using a RequestHandlerAdapter
 *   to conform to the PSR-15 MiddlewareInterface.
 *
 * If the middleware parameter is not a string, or if resolution from the container fails, the strategy returns null.
 */
final class ClassNameStrategy implements StrategyInterface
{
    /**
     * @param ContainerInterface $container The PSR-11 container used for retrieving middleware instances.
     */
    public function __construct(
        private readonly ContainerInterface $container
    ) {
    }

    /**
     * Attempts to resolve a middleware provided as a class name.
     *
     * This method checks if the provided middleware is a string representing a class name. It then:
     *  - Tries to get an instance from the container that implements MiddlewareInterface.
     *  - If not found, tries to get an instance that implements RequestHandlerInterface and wraps it
     *    in a RequestHandlerAdapter.
     *
     * @param mixed $middleware The middleware definition, expected to be a class name string.
     * @return MiddlewareInterface|null Returns a MiddlewareInterface instance if resolved, otherwise null.
     *
     * @throws ContainerExceptionInterface if an error occurs while accessing the container.
     */
    public function createMiddleware(mixed $middleware): ?MiddlewareInterface
    {
        return match (true) {
            !is_string($middleware) => null,
            ($obj = $this->get($middleware, MiddlewareInterface::class)) !== null => $obj,
            ($obj = $this->get($middleware, RequestHandlerInterface::class)) !== null => new RequestHandlerAdapter($obj),
            defaul => null
        };
    }

    /**
     * Retrieves an instance from the container if the given middleware class name is a subclass of the specified interface.
     *
     * This helper method checks whether the middleware class is a subclass of the provided interface (or class)
     * and if the container has a corresponding entry. If both conditions are met, it returns the instance from the container.
     *
     * @param string $middleware The middleware class name.
     * @param string $class The interface or class name to check against.
     * @return MiddlewareInterface|RequestHandlerInterface|null Returns the container instance if valid; otherwise, null.
     *
     * @throws ContainerExceptionInterface if an error occurs while retrieving the instance from the container.
     */
    private function get(string $middleware, string $class): null|MiddlewareInterface|RequestHandlerInterface
    {
        if (is_subclass_of($middleware, $class) && $this->container->has($middleware)) {
            return $this->container->get($middleware);
        }

        return null;
    }

    /**
     * Creates a new instance of ClassNameStrategy using a PSR-11 container.
     *
     * This static factory method instantiates a ClassNameStrategy by utilizing the provided container.
     *
     * @param ContainerInterface $container The PSR-11 container used to resolve middleware dependencies.
     * @return ClassNameStrategy A new instance of ClassNameStrategy.
     */
    public static function createFromContainer(ContainerInterface $container): ClassNameStrategy
    {
        return new self($container);
    }
}
