<?php

namespace Bermuda\MiddlewareFactory\Adapter;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Bermuda\MiddlewareFactory\Resolver\RequestAttributeResolver;
use Bermuda\ParameterResolver\ParameterResolver;
use Bermuda\ParameterResolver\ParameterResolverInterface;

/**
 * @internal
 */
class CallableAdapter implements MiddlewareInterface
{
    protected $callable;

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return ($this->callable)($request, $handler);
    }

    public static function adoptSinglePassMiddleware(callable $callable): CallableAdapter
    {
        return new class($callable) extends CallableAdapter
        {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return ($this->callable)($request, static fn (ServerRequestInterface $request): ResponseInterface => $handler->handle($request));
            }
        };
    }

    public static function adoptContainerParameterCallable(callable $callable, ContainerInterface $container): CallableAdapter
    {
        return new class($callable, $container) extends CallableAdapter {
            private ContainerInterface $container;

            public function __construct(callable $callback, ContainerInterface $container)
            {
                $this->container = $container;
                parent::__construct($callback);
            }

            /**
             * @inheritDoc
             */
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return ($this->callable)($this->container);
            }
        };
    }

    public static function adoptDoublePassMiddleware(callable $callable, ResponseFactoryInterface $factory): CallableAdapter
    {
        return new class($callable, $factory) extends CallableAdapter
        {
            public function __construct(callable $callable, private readonly ResponseFactoryInterface $responseFactory)
            {
                parent::__construct($callable);
            }

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return ($this->callable)($request, $this->responseFactory->createResponse(), static fn(ServerRequestInterface $request): ResponseInterface => $handler->handle($request));
            }
        };
    }

    /**
     * @param \ReflectionParameter[] $parameters
     */
    public static function adopt(
        callable $callable,
        ParameterResolver $resolver,
        array $parameters
    ): CallableAdapter
    {
        return new class($callable, $resolver, $parameters) extends CallableAdapter
        {
            private ParameterResolver $resolver;
            public function __construct(
                callable $callable,
                ParameterResolver $resolver,
                private readonly array $parameters
            ) {
                parent::__construct($callable);
                $this->resolver = $resolver;
            }

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return call_user_func_array($this->callable,
                    $this->resolver->resolve($this->parameters, [RequestAttributeResolver::REQUEST_PARAMETER_KEY => $request])
                );
            }
        };
    }
}
