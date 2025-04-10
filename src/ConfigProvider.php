<?php

namespace Bermuda\MiddlewareFactory;

use Bermuda\MiddlewareFactory\Strategy\CallableStrategy;
use Bermuda\MiddlewareFactory\Strategy\ClassNameStrategy;

class ConfigProvider extends \Bermuda\Config\ConfigProvider
{
    public const string CONFIG_KEY_STRATEGIES = 'Bermuda\MiddlewareFactory:strategies';

    protected function getFactories(): array
    {
        return [
            MiddlewareFactory::class => [MiddlewareFactory::class, 'createFromContainer'],
            CallableStrategy::class => [CallableStrategy::class, 'createFromContainer'],
            ClassNameStrategy::class => [ClassNameStrategy::class, 'createFromContainer'],
        ];
    }

    protected function getAliases(): array
    {
        return [MiddlewareFactoryInterface::class => MiddlewareFactory::class];
    }

    protected function getProviders(): array
    {
        return [\Bermuda\ParameterResolver\ConfigProvider::class];
    }
}
