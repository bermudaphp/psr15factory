<?php


namespace Bermuda\MiddlewareFactory\Decorator;


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Bermuda\MiddlewareFactory\MiddlewareFactoryInterface;


/**
 * Class PathDecorator
 * @package Bermuda\MiddlewareFactory\Decorator
 */
class PathDecorator implements MiddlewareInterface
{
    private $handler;
    private string $prefix;
    private MiddlewareFactoryInterface $factory;

    public function __construct(string $prefix, MiddlewareFactoryInterface $factory, $handler)
    {
        $this->handler = $handler;
        $this->factory = $factory;
        $this->prefix  = empty($prefix) ? '/' : $prefix;
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if($this->match((string) $request->getUri()))
        {
            return $this->factory->make($this->handler)->process($request, $handler);
        }
        
        return $handler->process($request);
    }
    
    private function match(string $path): bool
    {
        $segments = explode('/', ltrim($this->prefix, '/'));
        
        foreach(explode('/', ltrim($path, '/')) as $i => $segment)
        {
            if(strcasecmp($segments[$i], $segment) != 0)
            {
                return false;
            }
        }
        
        return true;
    }
}