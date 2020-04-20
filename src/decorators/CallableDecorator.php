<?php


namespace Lobster\Resolver\Decorators;


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;


/**
 * Class CallableMiddlewareDecorator
 * @package Lobster\Resolver\Decorators
 */
class CallableDecorator implements MiddlewareInterface
{

    /**
     * @var callable
     */
    protected $middleware;

    /**
     * @param callable $middleware
     * @return MiddlewareInterface
     */
    public static function route(callable $middleware) : MiddlewareInterface
    {
        return new class($middleware) extends CallableDecorator
        {
           function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
           {
               return ($this->middleware)($request);
           }
        };
    }

    /**
     * @param callable $middleware
     * @return MiddlewareInterface
     */
    public static function singlePass(callable $middleware) : MiddlewareInterface
    {
        return new self($middleware);
    }

    /**
     * @param callable $middleware
     * @param ResponseFactoryInterface $factory
     * @return MiddlewareInterface
     */
    public static function doublePass(callable $middleware, ResponseFactoryInterface $factory) : MiddlewareInterface
    {
        return new class($middleware, $factory) extends CallableDecorator
        {

            /**
             * @var ResponseFactoryInterface
             */
            private ResponseFactoryInterface $factory;

            /**
             * DoublePassMiddlewareDecorator constructor.
             * @param callable $middleware
             * @param ResponseFactoryInterface|null $factory
             */
            public function __construct(callable $middleware, ResponseFactoryInterface $factory)
            {
                $this->factory = $factory;
                parent::__construct($middleware);
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
        };
    }

    /**
     * CallableMiddlewareDecorator constructor.
     * @param callable $middleware
     */
    public function __construct(callable $middleware)
    {
        $this->middleware = $middleware;
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return ($this->middleware)($request, function (ServerRequestInterface $request) use ($handler) : ResponseInterface
        {
            return $handler->handle($request);
        });
    }
}
