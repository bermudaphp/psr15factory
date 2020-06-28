<?php


namespace Bermuda\MiddlewareFactory\Decorator;


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;


/**
 * Class DoublePassDecorator
 * @package Bermuda\MiddlewareFactory\Decorator
 */
final class DoublePassDecorator extends CallbackDecorator
{
  private ResponseFactoryInterface $factory;
  
  public function __construct(callable $callback, ResponseFactoryInterface $factory)
  {
      $this->factory = $factory;
      parent::__construct($callback);
  }

  /**
   * @inheritDoc
   */
  public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
  {
      return ($this->middleware)($request, $this->factory->createResponse(), static function (ServerRequestInterface $request) use ($handler): ResponseInterface
      {
          return $handler->handle($request);
      });
  }
}
