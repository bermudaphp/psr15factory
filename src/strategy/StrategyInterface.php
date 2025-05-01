<?php

namespace Bermuda\MiddlewareFactory\Strategy;

use Psr\Http\Server\MiddlewareInterface;

/**
 * Interface StrategyInterface
 *
 * Defines a contract for middleware resolution strategies. Implementations of this interface
 * should attempt to convert a given middleware definition into a valid PSR-15 MiddlewareInterface
 * instance.
 *
 * The makeMiddleware method accepts middleware definitions of various types and returns either:
 * - A valid MiddlewareInterface instance if the strategy can successfully resolve the definition.
 * - Null if the strategy does not apply to the provided middleware.
 *
 * This allows for a flexible, pluggable system in which different resolution strategies can be tried
 * to transform middleware definitions (e.g., callables, class names, pipelines) into middleware that
 * conforms to the PSR-15 standard.
 */
interface StrategyInterface
{
    /**
     * Attempts to resolve the provided middleware definition into a PSR-15 middleware.
     *
     * @param mixed $middleware The middleware definition to resolve.
     * @return MiddlewareInterface|null Returns a valid MiddlewareInterface instance if resolution is successful,
     *                                  or null if this strategy cannot handle the provided middleware.
     */
    public function makeMiddleware(mixed $middleware):? MiddlewareInterface;
}
