<?php


namespace Lobster\MiddlewareFactory\Decorator;


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;


/**
 * Class DoublePassDecorator
 * @package Lobster\MiddlewareFactory\Decorator
 */
class DoublePassDecorator extends CallbackDecorator
{
  private ResponseFactoryInterface $factory;
  
  public function __construct(callable $callback, ResponseFactoryInterface $factory)
  {
      $this->factory = $factory;
      parent::__construct($callback);
  }

            /**
             * @param ServerRequestInterface $request
             * @param RequestHandlerInterface $handler
             * @return ResponseInterface
             */
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return ($this->middleware)($request, $this->factory->createResponse(), function (ServerRequestInterface $request) use ($handler) : ResponseInterface
                {
                    return $handler->handle($request);
                });
            }
}
