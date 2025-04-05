<?php

namespace Bermuda\MiddlewareFactory\Strategy;

use Bermuda\MiddlewareFactory\Adapter\RequestHandlerAdapter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ClassNameStrategy implements StrategyInterface
{
    public function __construct(
        private readonly ContainerInterface $container
    ) {
    }

    /**
     * @throws ContainerExceptionInterface
     */
    public function makeMiddleware(mixed $middleware): ?MiddlewareInterface
    {
        if (!is_string($middleware)) return null;
        if (($obj = $this->try($middleware, MiddlewareInterface::class)) !== null) return $obj;
        if (($obj = $this->try($middleware, RequestHandlerInterface::class)) !== null) return new RequestHandlerAdapter($obj);

        return null;
    }

    /**
     * @throws ContainerExceptionInterface
     */
    private function try(string $middleware, string $class):null|MiddlewareInterface|RequestHandlerInterface
    {
        if (is_subclass_of($middleware, $class)) {
            try {
                return $this->container->get($middleware);
            } catch (NotFoundExceptionInterface) {
                return null;
            }
        }

        return null;
    }

    public static function createFromContainer(ContainerInterface $container): ClassNameStrategy
    {
        return new self($container);
    }
}
