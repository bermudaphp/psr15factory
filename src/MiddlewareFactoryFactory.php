<?php

namespace Bermuda\MiddlewareFactory;


use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Bermuda\MiddlewareFactory\PipelineFactoryInterface;


final class MiddlewareFactoryFactory
{
    public function __invoke(ContainerInterface $container): MiddlewareFactory
    {
        return new MiddlewareFactory($container, $container->get(ResponseFactoryInterface::class), $container->get(PipelineFactoryInterface::class));
    }
    
     /**
      * @param MiddlewareFactoryInterface[] $factories
      * @return AggregateMiddlewareFactory
      */
    public static function makeOf(iterable $factories = []): AggregateMiddlewareFactory
    {
        $aggregate = new AggregateMiddlewareFactory();
        
        foreach($factories as $factory)
        {
            $aggregate->addFactory($factory);
        }
        
        return $aggregate;
    }
}
