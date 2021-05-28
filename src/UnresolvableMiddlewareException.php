<?php

namespace Bermuda\MiddlewareFactory;

use Throwable;

/**
 * Class UnresolvableMiddlewareException
 * @package Bermuda\MiddlewareFactory
 */
final class UnresolvableMiddlewareException extends \RuntimeException
{
    private $middleware;
    
    public function __construct(?$message = null, $middleware = null)
    {
        $this->middleware = $middleware;
        
        if (!$message && is_string($middleware))
        {
            $message = 'Unresolvable middleware: ' . $middleware;
        }

        parent::__construct($message ?? 'Unresolvable middleware');
    }
    
    public function getMiddleware()
    {
        return $this->middleware;
    }
    
    public static function reThrow(UnresolvableMiddlewareException $e, array $backtrace): void
    {
        $self = new self($e->getMessage(), $e->getCommand());
        
        $self->file = $backtrace['file'];
        $self->line = $backtrace['line'];
        
        throw $self;
    }
    
    /**
     * @param \Throwable $e
     * @return static
     */
    public static function fromPrevios(\Throwable $e, $middleware): self
    {
        $self = new static($e->getMessage(), $e->getCode(), $e);
        
        $self->file = $e->getFile();
        $self->line = $e->getLine();
        
        return $self->setMiddleware($middleware);
    }
    
    /**
     * @param $any
     * @return static
     */
    public static function notCreatable($any): self
    {
        $type = Type::gettype($any, Type::objectAsClass);

        if ($type == Type::callable)
        {
            $type = static::getTypeForCallable($any);
        }
        
        return (new static('Cannot create middleware for this type: ' . $type))->setMiddleware($any);
    }
    
    /**
     * @param callable $any
     * @param string $returnType
     * @return static
     */
    public static function invalidReturnType(callable $any, string $returnType): self
    {
        return (new static(sprintf('Callable middleware should return an %s or %s. Returned: %s', ResponseInterface::class, MiddlewareInterface::class, $returnType)))
            ->setMiddleware($any);
    }
    
    private static function getTypeForCallable(callable $type): string
    {
        if (is_object($any))
        {
            return get_class($any);
        }
            
        if (is_array($any))
        {
            return (new \ReflectionMethod($any[0], $any[1]))->getName();
        }
        
        if (str_pos($any, '::') !== false)
        {
            return (new \ReflectionMethod($any))->getName();
        }
        
        return (new \ReflectionFunction($any))->getName();
    }
}
