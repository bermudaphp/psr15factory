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
            ['factories' => [MiddlewareFactoryInterface::class => function(ContainerInterface $c)
            {
                return new MiddlewareFactory($c, $c->get(ResponseFactoryInterface::class), $c->get(PipelineFactoryInterface::class));
            }]
        ]];
    }
}
