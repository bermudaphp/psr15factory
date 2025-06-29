<?php

namespace Bermuda\MiddlewareFactory;

/**
 * Interface for strategies that need access to MiddlewareFactory.
 *
 * Strategies implementing this interface will automatically receive
 * the MiddlewareFactory instance when added to the factory.
 */
interface MiddlewareFactoryAwareInterface
{
    /**
     * Sets the middleware factory instance.
     *
     * @param MiddlewareFactoryInterface $middlewareFactory The middleware factory
     * @return void
     */
    public function setMiddlewareFactory(MiddlewareFactoryInterface $middlewareFactory): void;
}