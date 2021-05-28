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
}
