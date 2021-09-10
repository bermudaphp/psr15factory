<?php

namespace Bermuda\MiddlewareFactory;

use Psr\Http\Server\MiddlewareInterface;

final class AggregateMiddlewareFactory implements MiddlewareFactoryInterface
{
    /**
     * @var MiddlewareFactoryInterface[]
     */
    private array $factories = [];

    public function addFactory(MiddlewareFactoryInterface $factory): self
    {
        $this->factories[get_class($factory)] = $factory;
        return $this;
    }

    public function hasFactory(string $class): bool
    {
        return isset($this->factories[$class]);
    }

    /**
     * @inheritDoc
     */
    public function __invoke($any): MiddlewareInterface
    {
        return $this->make($any);
    }

    /**
     * @inheritDoc
     */
    public function make($any): MiddlewareInterface
    {
        foreach ($this->factories as $factory) {
            try {
                return $factory->make($any);
            } catch (UnresolvableMiddlewareException $e) {
                continue;
            }
        }

        throw UnresolvableMiddlewareException::notCreatable($any);
    }
}
