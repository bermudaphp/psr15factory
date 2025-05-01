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
 * The createMiddleware method accepts middleware definitions of various types and returns either:
 * - A valid MiddlewareInterface instance if the strategy can successfully resolve the definition.
 * - Null if the strategy does not apply to the provided middleware.
 *
 * This approach enables a flexible, pluggable system where multiple resolution strategies can be
 * utilized to transform different middleware definitions (such as callables, class names, or pipelines)
 * into standard-compliant PSR-15 middleware.
 */
interface StrategyInterface
{
    /**
     * Attempts to resolve the provided middleware definition into a PSR-15 middleware.
     *
     * @param mixed $middleware The middleware definition to resolve.
     *                          This input can be of any type (e.g., a callable, a string indicating a class name,
     *                          or a pre-built middleware pipeline) as dictated by the specific resolution strategy.
     *
     * @return MiddlewareInterface|null Returns a valid MiddlewareInterface instance if the resolution is successful,
     *                                  or null if this strategy does not support the provided middleware definition.
     *
     * @throws MiddlewareResolutionExceptionInterface If an error occurs during the middleware resolution process.
     */
    public function createMiddleware(mixed $middleware):? MiddlewareInterface;
}
