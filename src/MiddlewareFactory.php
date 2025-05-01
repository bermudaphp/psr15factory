<?php

namespace Bermuda\MiddlewareFactory;

use Bermuda\ContainerAwareInterface;
use Bermuda\MiddlewareFactory\Strategy\MiddlewarePipelineStrategy;
use Bermuda\Pipeline\PipelineFactoryInterface;
use Bermuda\MiddlewareFactory\Adapter\RequestHandlerAdapter;
use Bermuda\MiddlewareFactory\Strategy\CallableStrategy;
use Bermuda\MiddlewareFactory\Strategy\ClassNameStrategy;
use Bermuda\MiddlewareFactory\Strategy\StrategyInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function Bermuda\Config\conf;

/**
 * MiddlewareFactory is responsible for converting middleware definitions into valid
 * PSR-15 middleware instances. This is accomplished by iterating over a collection of
 * resolution strategies (StrategyInterface instances), each attempting to resolve the given
 * middleware definition.
 *
 * If no strategy is able to resolve the middleware and the given definition is not already a
 * MiddlewareInterface or RequestHandlerInterface, an UnresolvableMiddlewareException is thrown.
 */
final class MiddlewareFactory implements MiddlewareFactoryInterface
{
    /**
     * @var StrategyInterface[]
     */
    private array $strategies = [];

    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * Resolves a middleware definition to a valid PSR-15 MiddlewareInterface instance.
     *
     * The method iterates over the registered strategies and attempts to resolve the given middleware definition.
     * When a strategy successfully returns a middleware instance, that instance is returned immediately.
     * If an exception occurs during resolution, it is wrapped (if needed) and enriched with backtrace information.
     *
     * Additionally, if the provided middleware is already a MiddlewareInterface or a RequestHandlerInterface,
     * it is adapted accordingly.
     *
     * @param mixed $any The middleware definition to be resolved.
     * @return MiddlewareInterface A valid middleware instance.
     *
     * @throws MiddlewareResolutionExceptionInterface If the middleware cannot be resolved to a valid instance.
     */
    public function createMiddleware(mixed $any): MiddlewareInterface
    {
        foreach ($this->strategies as $strategy) {
            try {
                $middleware = $strategy->makeMiddleware($any);
                if ($middleware) return $middleware;
            } catch (\Throwable $e) {
                if (!$e instanceof MiddlewareResolutionExceptionInterface) $e = MiddlewareResolutionException::createFromPrev($any, $e);
                if ($e instanceof BacktraceAwareInterface) $e->setBacktrace(debug_backtrace()[0]);

                throw $e;
            }
        }

        if ($any instanceof MiddlewareInterface) return $any;
        if ($any instanceof RequestHandlerInterface) return new RequestHandlerAdapter($any);


        $e = throw new MiddlewareResolutionException($any, 'Canno\'t resolve middleware');
        $e->setBacktrace(debug_backtrace()[0]);

        throw $e;
    }


    /**
     * Registers a new strategy for resolving middleware definitions.
     *
     * If the provided strategy implements ContainerAwareInterface, the container is injected into it.
     * The strategy is then appended (or prepended, based on the flag) to the internal list of strategies.
     *
     * @param StrategyInterface $strategy The strategy instance to be added.
     * @param bool $prepend If true, the strategy is added at the beginning of the list.
     */
    public function addStrategy(StrategyInterface $strategy, bool $prepend = false): void
    {
        if ($strategy instanceof ContainerAwareInterface) $strategy->setContainer($this->container);
        if ($prepend) array_unshift($this->strategies, $strategy);
        else $this->strategies[] = $strategy;
    }

    /**
     * Creates a new MiddlewareFactory instance using a PSR-11 container.
     *
     * This static factory method retrieves configuration settings and strategy definitions from the container,
     * pre-populating the MiddlewareFactory with both custom and default strategies.
     *
     * Custom strategies are loaded based on configuration under the key specified by
     * ConfigProvider::CONFIG_KEY_STRATEGIES. Additionally, default strategies such as ClassNameStrategy,
     * CallableStrategy, and MiddlewarePipelineStrategy are appended.
     *
     * @param ContainerInterface $container The container used to resolve strategy dependencies.
     *
     * @return self A fully configured instance of MiddlewareFactory.
     *
     * @throws NotFoundExceptionInterface If a strategy dependency is not found in the container.
     * @throws ContainerExceptionInterface If an error occurs while retrieving a dependency from the container.
     */
    public static function createFromContainer(ContainerInterface $container): self
    {
        $config = conf($container);

        $factory = new self($container);

        foreach ($config->get(ConfigProvider::CONFIG_KEY_STRATEGIES, []) as $strategy) {
            if (is_string($strategy)) $strategy = $container->get($strategy);
            $factory->addStrategy($strategy);
        }

        $factory->addStrategy($container->get(ClassNameStrategy::class));
        $factory->addStrategy($container->get(CallableStrategy::class));
        $factory->addStrategy($container->get(MiddlewarePipelineStrategy::class));

        return $factory;
    }
}
