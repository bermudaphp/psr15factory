<?php

namespace Bermuda\MiddlewareFactory;


final class MiddlewareFactoryFactory
{
    public function __invoke(ContainerInterface $container): MiddlewareFactory
    {
        return new MiddlewareFactory($container, $container->get(ResponseFactoryInterface::class), $container->get(PipelineFactoryInterface::class));
    }
    
    public static function makeOf(array $factories = []): AggregateMiddlewareFactory
    {
        $aggregate = new AggregateMiddlewareFactory();
        
        foreach($factories as $factory)
        {
            $aggregate->addFactory($factory);
        }
        
        return $aggregate;
    }
}
