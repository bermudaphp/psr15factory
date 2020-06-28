<?php


namespace Lobster\MiddlewareFactory;


use Lobster\Type;


/**
 * Class MiddlewareFactoryException
 * @package Lobster\MiddlewareFactory
 */
class MiddlewareFactoryException extends \RuntimeException 
{
    /**
     * @param $middleware
     * @throws static
     */
    public function throw() : void
    {
        throw this;
    }
    
    /**
     * @param $any
     * @return static
     */
    public static notCreatable($any) : self
    {
        $type = Type::gettype($any, Type::objectAsClass);

        if ($type == Type::callable)
        {
            $type = static::getTypeForCallable($any);
        }
        
        return new static('Cannot create middleware for this type: ' . $type);
    }
    
    /**
     * @param callable $any
     * @param string $returnType
     * @return static
     */
    public static invalidReturnType(callable $any, string $returnType) : void 
    {
        return new static('Callable: ' . $type . 'should return an Psr\Http\Message\ResponseInterface. Returned '. $returnType);
    }
    
    private static function getTypeForCallable(callable $type) : string
    {
        if(is_object($any))
        {
            return get_class($any);
        }
            
        if(is_array($any))
        {
            return new \ReflectionMethod($any[0], $any[1])->getName();
        }
        
        if(str_pos($any, '::') !== false)
        {
            return new \ReflectionMethod($any)->getName();
        }
        
        return new \ReflectionFunction($any)->getName();
    }
}