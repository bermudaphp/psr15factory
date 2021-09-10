<?php

namespace Bermuda\MiddlewareFactory;

use Bermuda\Pipeline\PipelineFactoryInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

final class MiddlewareFactoryFactory
{
    public static function makeOf(iterable $factories = []): AggregateMiddlewareFactory
    {
        $aggregate = new AggregateMiddlewareFactory();

        foreach ($factories as $factory) {
            $aggregate->addFactory($factory);
        }

        return $aggregate;
    }

    public function __invoke(ContainerInterface $container): MiddlewareFactory
    {
        return new MiddlewareFactory($container, $container->get(ResponseFactoryInterface::class), $container->get(PipelineFactoryInterface::class));
    }
}
