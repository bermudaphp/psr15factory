<?php

namespace MiddlewareFactory;

use Bermuda\ContainerAwareInterface;
use Bermuda\Pipeline\PipelineFactoryInterface;
use MiddlewareFactory\adapter\RequestHandlerAdapter;
use MiddlewareFactory\strategy\CallableStrategy;
use MiddlewareFactory\strategy\ClassNameStrategy;
use MiddlewareFactory\strategy\StrategyInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function Bermuda\Config\conf;

final class MiddlewareFactory implements MiddlewareFactoryInterface
{
    /**
     * @var StrategyInterface[]
     */
    private array $strategies = [];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly PipelineFactoryInterface $pipelineFactory,
    ) {

    }

    public function makeMiddleware(mixed $any): MiddlewareInterface
    {
        foreach ($this->strategies as $strategy) {
            try {
                $middleware = $strategy->makeMiddleware($any);
                if ($middleware) return $middleware;
            } catch (UnresolvableMiddlewareException $e) {
                throw $e->setBacktrace(debug_backtrace()[0]);
            } catch (\Throwable $e) {
                throw UnresolvableMiddlewareException::fromPrev($e, $any);
            }
        }

        if ($any instanceof MiddlewareInterface) return $any;
        if ($any instanceof RequestHandlerInterface) return new RequestHandlerAdapter($any);

        if (is_iterable($any)) {
            $pipeline = $this->pipelineFactory->make();
            foreach ($any as $middleware) $pipeline->pipe($this->makeMiddleware($middleware));

            return $pipeline;
        }

        throw new UnresolvableMiddlewareException(middleware: $any);
    }

    public function addStrategy(StrategyInterface $strategy): void
    {
        if ($strategy instanceof ContainerAwareInterface) $strategy->setContainer($this->container);
        $this->strategies[] = $strategy;
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public static function createFromContainer(ContainerInterface $container): self
    {
        $config = conf($container);
        $factory = new self($container, $container->get(PipelineFactoryInterface::class));
        foreach (array_merge([ClassNameStrategy::class, CallableStrategy::class],
            $config->get(ConfigProvider::CONFIG_KEY_STRATEGIES, [])) as $strategy) {
            $factory->addStrategy($container->get($strategy));
        }

        return $factory;
    }
}