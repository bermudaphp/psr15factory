<?php

namespace Bermuda\MiddlewareFactory;

use Psr\Http\Server\MiddlewareInterface;

/**
 * Interface MiddlewareFactoryInterface
 * @package Bermuda\MiddlewareFactory
 */
interface MiddlewareFactoryInterface
{
    /**
     * @param mixed $any
     * @return MiddlewareInterface
     * @throws UnresolvableMiddlewareException
     */
    public function make($any): MiddlewareInterface ;
    
    /**
     * Alias for self::make 
     */
    public function __invoke($any) : MiddlewareInterface ;
}
