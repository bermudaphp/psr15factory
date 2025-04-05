<?php

namespace Bermuda\MiddlewareFactory\Attribute;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

#[\Attribute(\Attribute::TARGET_PARAMETER)]class Config
{
    public function __construct(
        public readonly string|array $path,
        public readonly string $configKey = 'config',
    ) {}

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getParameter(ContainerInterface $container, \ReflectionParameter $parameter): array
    {
        $config = $container->get($this->configKey);
        $path = $this->path;
        if (is_array($path)) {
            $entry = $config[array_shift($path)];
            while (($key = array_shift($path)) !== null) {
                $entry = $entry[$key];
            }
        } else $entry = $config[$path];
        return [$parameter->getName(), $entry];
    }
}
