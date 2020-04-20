<?php


namespace Lobster\Resolver\Decorators;


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;


/**
 * Class RequestHandlerDecorator
 * @package Lobster\Resolver\Decorators
 */
class RequestHandlerDecorator implements RequestHandlerInterface, MiddlewareInterface
{

    /**
     * @var RequestHandlerInterface
     */
    private RequestHandlerInterface $handler;

    /**
     * RequestHandlerDecorator constructor.
     * @param RequestHandlerInterface $handler
     */
    public function __construct(RequestHandlerInterface $handler)
    {
        $this->handler = $handler;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handler->handle($request);
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->handler->handle($request);
    }
}
