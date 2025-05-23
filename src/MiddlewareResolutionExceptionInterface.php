<?php

namespace Bermuda\MiddlewareFactory;

/**
 * Interface MiddlewareResolutionExceptionInterface
 *
 * This interface defines a contract for exceptions related to middleware resolution.
 * Any exception that occurs during the process of resolving a middleware should implement
 * this interface to expose the original middleware definition that triggered the error.
 *
 * @property-read mixed $middleware The middleware definition that could not be resolved.
 */
interface MiddlewareResolutionExceptionInterface extends \Throwable
{
    /**
     * Read-only property to access the middleware that caused the exception.
     *
     * Implementers should ensure that the original middleware definition is stored
     * and made accessible via this property.
     *
     * @return mixed
     */
    public mixed $middleware { get; }
}