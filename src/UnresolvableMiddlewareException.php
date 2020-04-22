<?php


namespace Lobster\Resolver;


use Lobster\Type;


/**
 * Class UnresolvableMiddlewareException
 * @package Lobster\Resolver
 */
class UnresolvableMiddlewareException extends \RuntimeException 
{

    /**
     * @param $middleware
     * @throws static
     */
    public static function throw($middleware) : void 
    {

        $type = Type::gettype($middleware, Type::objectAsClass);

        if ($type == Type::callable)
        {
            if(is_object($middleware))
            {
                $type = get_class($middleware);
            }

            $type = (new \ReflectionFunction($middleware))->getName();
        }
        
        throw new static('Unresolvable middleware: ' . $type);
    }
}
