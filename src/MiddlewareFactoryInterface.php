<?php

namespace Bermuda\MiddlewareFactory;

use Psr\Http\Server\MiddlewareInterface;

/**
 * MiddlewareFactoryInterface defines a contract for creating a PSR-15 middleware
 * instance from arbitrary input.
 *
 * Implementers of this interface must construct and return an instance of MiddlewareInterface
 * based on the provided input parameter. If the middleware cannot be resolved, an exception
 * that implements MiddlewareResolutionExceptionInterface should be thrown.
 */
interface MiddlewareFactoryInterface
{
    /**
     * Creates and returns a PSR-15 middleware instance based on the supplied input.
     *
     * @param mixed $any The input data used to resolve or construct the middleware instance.
     *
     * @return MiddlewareInterface The created middleware instance.
     *
     * @throws MiddlewareResolutionExceptionInterface If the middleware cannot be resolved.
     */
    public function createMiddleware(mixed $any): MiddlewareInterface;
}
