<?php

namespace Bermuda\MiddlewareFactory\Decorator;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CallbackDecorator implements MiddlewareInterface
{
    /**
     * @var callable
     */
    protected $callback;

    /**
     * @return static
     */
    public static function decorateEmptyArgsCallable(callable $callable): self
    {
        return new class($callable) extends CallbackDecorator {
            /**
             * @inheritDoc
             */
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return ($this->callback)();
            }
        };
    }

    /**
     * @return static
     */
    public static function decorateContainerArgCallable(callable $callable, ContainerInterface $container): self
    {
        return new class($callable, $container) extends CallbackDecorator {
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
                return ($this->callback)($this->container);
            }
        };
    }

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * @return callable
     */
    public function getCallback(): callable
    {
        return $this->callback;
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return ($this->callback)($request, $handler);
    }
}
