<?php

namespace Bermuda\MiddlewareFactory\Attribute;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

#[\Attribute(\Attribute::TARGET_PARAMETER)] class Container
{
    public function __construct(
        public readonly string $id,
    ) {
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getParameter(ContainerInterface $container, \ReflectionParameter $parameter): array
    {
        return [$parameter->getName(), $container->get($this->id)];
    }
}
