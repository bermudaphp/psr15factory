<?php

namespace Bermuda\MiddlewareFactory;


use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Bermuda\Pipeline\PipelineFactoryInterface;


/**
 * Class ConfigProvider
 * @package Bermuda\Cycle
 */
class ConfigProvider extends \Bermuda\Config\ConfigProvider
{
    protected function getFactories(): array
    {
        return [MiddlewareFactoryInterface::class => MiddlewareFactoryFactory::class];
    }
}
