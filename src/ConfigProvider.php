<?php

namespace Bermuda\MiddlewareFactory;


/**
 * Class ConfigProvider
 * @package Bermuda\MiddlewareFactory
 */
class ConfigProvider extends \Bermuda\Config\ConfigProvider
{
    protected function getFactories(): array
    {
        return [MiddlewareFactoryInterface::class => MiddlewareFactoryFactory::class];
    }
}
