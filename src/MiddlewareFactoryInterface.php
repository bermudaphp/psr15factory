<?php

namespace Bermuda\MiddlewareFactory;

use Psr\Http\Server\MiddlewareInterface;

/**
 * Interface MiddlewareFactoryInterface
 *
 * This interface defines a factory for creating PSR-15 MiddlewareInterface instances
 * from various middleware definitions.
 *
 * The makeMiddleware method accepts an input of any type and tries to resolve it into
 * a valid MiddlewareInterface instance. If the middleware cannot be resolved, it throws
 * an UnresolvableMiddlewareException.
 */
interface MiddlewareFactoryInterface
{
    /**
     * Resolves the provided middleware definition into a PSR-15 middleware instance.
     *
     * @param mixed $any The middleware definition to be resolved.
     * @return MiddlewareInterface A valid PSR-15 middleware instance.
     * @throws MiddlewareResolutionExceptionInterface If the middleware cannot be resolved.
     */
    public function makeMiddleware(mixed $any): MiddlewareInterface;
}
