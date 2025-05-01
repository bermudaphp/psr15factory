<?php

namespace Bermuda\MiddlewareFactory\Strategy;

use Bermuda\MiddlewareFactory\Adapter\CallableAdapter;
use Bermuda\DI\CallableExecutorInterface;
use Bermuda\DI\CallableResolutionExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Reflection\Reflection;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * CallableStrategy adapts a middleware defined as a callable into a PSR-15 compliant MiddlewareInterface.
 *
 * This strategy uses reflection to analyze the callable's signature and determine the correct adaptation:
 * - For single-pass middleware (two parameters, where the second is callable), it creates a single-pass adapter.
 * - For double-pass middleware (three parameters, with the second parameter type-hinted as ResponseInterface
 *   and the third declared as callable), it creates a double-pass adapter.
 * - For all other cases, it wraps the callable in a generic CallableAdapter.
 */
final class CallableStrategy implements StrategyInterface
{
    public function __construct(
        private readonly CallableExecutorInterface $executor,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    /**
     * Creates a MiddlewareInterface instance from the given middleware.
     *
     * The middleware argument can be any callable resolvable by the executor. This method inspects its
     * signature via reflection:
     * - If the callable expects a ServerRequestInterface and one callable argument, it's treated as single-pass middleware.
     * - If the callable expects a ServerRequestInterface, a ResponseInterface, and a callable argument,
     *   it's treated as double-pass middleware.
     * - Otherwise, the callable is wrapped in a standard CallableAdapter.
     *
     * @param mixed $middleware The middleware callable or middleware definition.
     * @return MiddlewareInterface|null Returns the adapted middleware or null if resolution failed.
     * @throws CallableResolverExceptionInterface When callable resolution fails.
     */
    public function makeMiddleware(mixed $middleware): ?MiddlewareInterface
    {
        $callable = $this->executor->resolve($middleware);
        if (!$callable) return null;

        $reflector = Reflection::callable($callable);

        $count = count($parameters = $reflector->getParameters());
        $isServerRequest = $this->isParameterTypeCompatible($parameters[0], ServerRequestInterface::class);

        if ($count > 0 && $isServerRequest) {
            if ($this->isSinglePassMiddleware($count, $parameters)) {
                return CallableAdapter::singlePassMiddleware($middleware, $this->executor);
            }

            if ($this->isDoublePassMiddleware($count, $parameters)) {
                return CallableAdapter::doublePassMiddleware($middleware, $this->executor, $this->responseFactory);
            }
        }

        return new CallableAdapter($callable, $this->executor);
    }

    private function isSinglePassMiddleware(int $count, array $parameters): bool
    {
        return $count == 2 && $this->declaresCallable($parameters[1]);
    }

    private function isDoublePassMiddleware(int $count, array $parameters): bool
    {
        return $count == 3 && $this->isParameterTypeCompatible($parameters[1], ResponseInterface::class)
            && $this->declaresCallable($parameters[2]);
    }

    /**
     * @param ReflectionParameter $parameter
     * @param string $type
     * @return bool
     */
    private function isParameterTypeCompatible(ReflectionParameter $parameter, string $type): bool
    {
        if (!($refType = $parameter->getType()) instanceof ReflectionNamedType) {
            return false;
        }

        return ($typeName = $refType->getName()) == $type || is_subclass_of($typeName, $type);
    }

    /**
     * @param ReflectionParameter $reflectionParameter
     * @return bool
     */
    private function declaresCallable(ReflectionParameter $reflectionParameter): bool
    {
        $reflectionType = $reflectionParameter->getType();
        if (!$reflectionType) return false;

        $types = $reflectionType instanceof \ReflectionUnionType
            ? $reflectionType->getTypes()
            : [$reflectionType];

        return array_any($types, static fn(\ReflectionNamedType $type) => $type->getName() == 'callable');

    }

    /**
     * Creates an instance of CallableStrategy using a PSR-11 container.
     *
     * This method retrieves required dependencies from the container: CallableExecutorInterface and ResponseFactoryInterface.
     *
     * @param ContainerInterface $container The container used to retrieve dependencies.
     * @return CallableStrategy The constructed CallableStrategy instance.
     * @throws ContainerExceptionInterface If there is an error while retrieving an entry.
     * @throws NotFoundExceptionInterface If a required dependency is not found.
     */
    public static function createFromContainer(ContainerInterface $container): CallableStrategy
    {
        return new static(
            $container->get(CallableExecutorInterface::class),
            $container->get(ResponseFactoryInterface::class)
        );
    }
}
