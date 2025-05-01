<?php

namespace Bermuda\MiddlewareFactory;

use Bermuda\MiddlewareFactory\Resolver\RequestAttributeResolver;
use Bermuda\MiddlewareFactory\Resolver\RequestMapperResolver;
use Bermuda\MiddlewareFactory\Strategy\CallableStrategy;
use Bermuda\MiddlewareFactory\Strategy\ClassNameStrategy;
use Bermuda\MiddlewareFactory\Strategy\MiddlewarePipelineStrategy;
use Bermuda\ParameterResolver\ParameterResolver;
use Bermuda\ParameterResolver\ParameterResolverInterface;
use Bermuda\DI\FactoryInterface;
use Psr\Container\ContainerInterface;

class ConfigProvider extends \Bermuda\Config\ConfigProvider
{
    public const string CONFIG_KEY_STRATEGIES = 'Bermuda\MiddlewareFactory:strategies';

    protected function getFactories(): array
    {
        return [
            MiddlewareFactory::class => [MiddlewareFactory::class, 'createFromContainer'],
            CallableStrategy::class => [CallableStrategy::class, 'createFromContainer'],
            ClassNameStrategy::class => [ClassNameStrategy::class, 'createFromContainer'],
            MiddlewarePipelineStrategy::class => [MiddlewarePipelineStrategy::class, 'createFromContainer'],
            RequestMapperResolver::class => [self::class, 'createRequestMapperResolver'],
            ParameterResolver::class => [self::class, 'createParameterResolver']
        ];
    }

    protected function getAliases(): array
    {
        return [MiddlewareFactoryInterface::class => MiddlewareFactory::class];
    }

    protected function getInvokables(): array
    {
        return [RequestAttributeResolver::class];
    }
    
    private function createRequestMapperResolver(ContainerInterface $container): RequestMapperResolver
    {
        return new RequestMapperResolver($container->get(FactoryInterface::class));
    }

    private function createParameterResolver(ContainerInterface $container): ParameterResolver
    {
        if ($container->has(ParameterResolver::class)) {
            $resolver = $container->get(ParameterResolver::class);
        } else $resolver = ParameterResolver::createDefaults($container);

        $resolver->addResolver($container->get(RequestAttributeResolver::class), true);
        $resolver->addResolver($container->get(RequestMapperResolver::class), true);
        
        return $resolver;
    }
}
