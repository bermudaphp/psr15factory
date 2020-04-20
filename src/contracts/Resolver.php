<?php


namespace Lobster\Resolver\Contracts;


use Psr\Http\Server\MiddlewareInterface;


/**
 * Interface ResolverInterface
 * @package Lobster\Resolver
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
