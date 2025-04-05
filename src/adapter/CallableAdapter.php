<?php

namespace Bermuda\MiddlewareFactory\Adapter;

use Invoker\InvokerInterface;
use Bermuda\MiddlewareFactory\Attribute\Config;
use Bermuda\MiddlewareFactory\Attribute\Container;
use Bermuda\MiddlewareFactory\Attribute\RequestAttributes;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

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
     * @param callable $callable
     * @param ContainerInterface $container
     * @param \ReflectionParameter[] $parameters
     * @return CallableAdapter
     */
    public static function adoptAttributes(
        callable $callable,
        ContainerInterface $container,
        InvokerInterface $invoker,
        array $parameters
    ): CallableAdapter
    {
        return new class($callable, $container, $invoker, $parameters) extends CallableAdapter
        {
            public function __construct(
                callable $callable,
                private readonly ContainerInterface $container,
                private readonly InvokerInterface $invoker,
                private readonly array $parameters
            ) {
                parent::__construct($callable);
            }

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $params = [];
                foreach ($this->parameters as $parameter) {
                    $attribute = $this->getAttribute($parameter, [Container::class, Config::class]);
                    if ($attribute) {
                        list($key, $value) = $attribute->getParameter($this->container, $parameter);
                        $params[$key] = $value;
                        continue;
                    }

                    $attribute = $this->getAttribute($parameter, RequestAttributes::class);
                    if ($attribute) {
                        list($key, $value) = $attribute->getParameter($request, $parameter);
                        $params[$key] = $value;
                    }
                }

                return $this->invoker->call($this->callable, $params);
            }

            private function getAttribute(\ReflectionParameter $parameter, string|array $classes): null|Config|Container|RequestAttributes
            {
                is_array($classes)?: $classes = [$classes];
                foreach ($classes as $class) {
                    $a = $parameter->getAttributes($class)[0] ?? null;
                    if ($a) return $a->newInstance();
                }

                return null;
            }
        };
    }
}
