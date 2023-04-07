<?php

namespace Bermuda\MiddlewareFactory;

use Bermuda\MiddlewareFactory\Decorator\RequestHandlerDecorator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ClassnameFactory implements MiddlewareFactoryInterface
{
    public function __construct(
        private readonly ContainerInterface $container
    ) {
    }

    /**
     * @inheritDoc
     */
    public function make($any): MiddlewareInterface
    {
        try {
            $middleware = $container->get($any);
            if ($middleware instanceof MiddlewareInterface) return $middleware;
            if ($middleware instanceof RequestHandlerInterface) return new RequestHandlerDecorator($middleware);
            throw UnresolvableMiddlewareException::notCreatable($any);
        } catch (ContainerExceptionInterface|NotFoundExceptionInterface $e) {
            throw UnresolvableMiddlewareException::fromPrevious($e, $any);
        }
    }
    
    /**
     * @inheritDoc
     */
    public function __invoke($any): MiddlewareInterface
    {
        return $this->make($any);
    }
}
