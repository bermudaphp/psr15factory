<?php


namespace Bermuda\MiddlewareFactory\Decorator;


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;


/**
 * Class PathDecorator
 * @package Bermuda\MiddlewareFactory\Decorator
 */
class PathDecorator implements RequestHandlerInterface, MiddlewareInterface
{
    private $handler;
    private MiddlewareFactoryInterface $factory;

    public function __construct(MiddlewareFactoryInterface $factory, $handler)
    {
        $this->handler = $handler;
        $this->factory = $factory;
    }

    /**
     * @inheritDoc
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handler->handle($request);
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->handler->handle($request);
    }
}
