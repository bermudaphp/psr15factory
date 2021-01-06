<?php

namespace Bermuda\MiddlewareFactory;


use Bermuda\Pipeline\PipelineFactory;
use Bermuda\Pipeline\PipelineFactoryInterface;


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
