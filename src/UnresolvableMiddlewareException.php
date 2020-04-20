<?php


namespace Lobster\Resolver;


use Lobster\Type;


/**
 * Class UnresolvableMiddlewareException
 * @package Lobster\Resolver
 */
class UnresolvableMiddlewareException extends \RuntimeException {

    /**
     * @param $middleware
     * @throws static
     */
    public static function throw($middleware) : void {
        throw new static('Unresolvable middleware: ' . Type::gettype($middleware));
    }
}
