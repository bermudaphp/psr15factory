<?php


namespace Lobster\MiddlewareFactory;


use Psr\Http\Server\MiddlewareInterface;


/**
 * Interface MiddlewareFactoryInterface
 * @package Lobster\MiddlewareFactory
 */
interface MiddlewareFactoryInterface
{
    /**
     * @param mixed $any
     * @return MiddlewareInterface
     * @throws MiddlewareFactoryException
     */
    public function make($any): MiddlewareInterface ;
    
    /**
     * Alias for $this->make()
     */
    public function __invoke($any) : MiddlewareInterface ;
}
