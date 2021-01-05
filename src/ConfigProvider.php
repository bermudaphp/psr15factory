<?php

namespace Bermuda\MiddlewareFactory;


use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Bermuda\Pipeline\PipelineFactoryInterface;


final class ConfigProvider
{
    public function __invoke(): array
    {
        return ['dependencies' => 
            ['factories' => [MiddlewareFactoryInterface::class => static function(ContainerInterface $container)
            {
                return new MiddlewareFactory($container, $container->get(ResponseFactoryInterface::class), $container->get(PipelineFactoryInterface::class));
            }]
        ]];
    }
}
