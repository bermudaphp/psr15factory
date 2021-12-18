<?php

namespace Bermuda\MiddlewareFactory;

use Bermuda\Pipeline\PipelineFactoryInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

final class MiddlewareFactoryFactory
{
    public static function makeOf(iterable $factories = []): AggregateMiddlewareFactory
    {
        return AggregateMiddlewareFactory::fromFactories($factories);
    }

    public function __invoke(ContainerInterface $container): MiddlewareFactory
    {
        return MiddlewareFactory::fromContainer($container);
    }
}
