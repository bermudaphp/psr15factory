<?php


namespace Lobster\MiddlewareFactory\Decorator;


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;


/**
 * Class CallabackDecorator
 * @package Lobster\MiddlewareFactory\Decorator
 */
class CallbackDecorator implements MiddlewareInterface
{
    /**
     * @var callable
     */
    protected $callback;
    
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }
    
    /**
     * @inheritDoc
     */
    function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return ($this->callback)($request, $handler);
    }
}
