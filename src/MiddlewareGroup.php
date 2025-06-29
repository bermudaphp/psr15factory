<?php

namespace Bermuda\MiddlewareFactory;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Container for middleware definitions that can be converted to Pipeline.
 *
 * Accepts any middleware definitions:
 * - Strings (class names, service names)
 * - Arrays [service, method]
 * - Callables
 * - MiddlewareInterface instances
 * - Other MiddlewareGroup instances
 */
final class MiddlewareGroup implements \Countable, \IteratorAggregate
{
    /**
     * Array of middleware definitions.
     */
    private(set) array $middlewares = [];

    /**
     * Creates a new middleware group from definitions.
     *
     * @param iterable $middlewares Iterable set of middleware definitions
     */
    public function __construct(
        iterable $middlewares = [],
    ) {
        foreach ($middlewares as $middleware) {
            $this->middlewares[] = $middleware;
        }
    }

    /**
     * Adds a middleware definition to the group.
     *
     * @param mixed $middleware Middleware definition to add
     * @param bool $prepend Whether to add at the beginning of the group
     * @return self New instance with the added middleware
     */
    public function add(mixed $middleware, bool $prepend = false): self
    {
        $copy = clone $this;

        if ($prepend) {
            array_unshift($copy->middlewares, $middleware);
        } else {
            $copy->middlewares[] = $middleware;
        }

        return $copy;
    }

    /**
     * Adds multiple middleware definitions to the group.
     *
     * @param iterable $middlewares Set of middleware definitions
     * @param bool $prepend Whether to add at the beginning of the group
     * @return self New instance with the added middlewares
     */
    public function addMany(iterable $middlewares, bool $prepend = false): self
    {
        $copy = clone $this;

        $middlewareArray = [];
        foreach ($middlewares as $middleware) {
            $middlewareArray[] = $middleware;
        }

        if ($prepend) {
            foreach (array_reverse($middlewareArray) as $middleware) {
                array_unshift($copy->middlewares, $middleware);
            }
        } else {
            foreach ($middlewareArray as $middleware) {
                $copy->middlewares[] = $middleware;
            }
        }

        return $copy;
    }

    /**
     * Checks if the group is empty.
     *
     * @return bool True if the group contains no middleware
     */
    public function isEmpty(): bool
    {
        return empty($this->middlewares);
    }

    /**
     * Returns the number of middleware in the group.
     *
     * @return int Number of middleware definitions
     */
    public function count(): int
    {
        return count($this->middlewares);
    }

    /**
     * Returns an iterator for traversing middleware definitions.
     *
     * @return \ArrayIterator Iterator for middleware definitions
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->middlewares);
    }
}