<?php


namespace Lobster\Resolver\Contracts;


use Psr\Http\Server\MiddlewareInterface;


/**
 * Interface Resolver
 * @package Lobster\Resolver\Contracts
 */
interface Resolver
{
    /**
     * @param $middleware
     * @return MiddlewareInterface
     * @throws UnresolvableMiddlewareException
     */
    public function resolve($middleware): MiddlewareInterface ;
}
