<?php

namespace Bermuda\MiddlewareFactory\Strategy;

use Bermuda\CheckType\Type;
use Invoker\Exception\InvocationException;
use Invoker\Exception\NotCallableException;
use Invoker\Exception\NotEnoughParametersException;
use Invoker\InvokerInterface;
use Bermuda\MiddlewareFactory\Adapter\CallableAdapter;
use Bermuda\MiddlewareFactory\Adapter\RequestHandlerAdapter;
use Bermuda\MiddlewareFactory\Attribute\Config;
use Bermuda\MiddlewareFactory\Attribute\Container;
use Bermuda\MiddlewareFactory\UnresolvableMiddlewareException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionNamedType;
use ReflectionParameter;

class CallableStrategy implements StrategyInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly InvokerInterface   $invoker,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    /**
     * @param mixed $middleware
     * @return MiddlewareInterface|null
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \ReflectionException
     */
    public function makeMiddleware(mixed $middleware): ?MiddlewareInterface
    {
        $reflector = null;

        if (is_callable($middleware)) {
            if (is_object($middleware)) $reflector = new \ReflectionMethod($middleware, '__invoke');
            elseif (is_array($middleware)) $reflector = new \ReflectionMethod($middleware[0], $middleware[1]);
            elseif (is_string($middleware)) {
                if (str_contains($middleware, '::')) $reflector = $this->getReflector($middleware);
                else $reflector = new \ReflectionFunction($middleware);
            }
        }

        else if (is_string($middleware)) {
            if (str_contains($middleware, '::')) {
                $reflector = $this->getReflector($middleware);
                $middleware = [$this->container->get($reflector->class), $reflector->name];
            } else {
                try {
                    $reflector = new \ReflectionMethod($middleware, '__invoke');
                    $middleware = [$this->container->get($middleware), '__invoke'];
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        if (!$reflector) return null;

        $returnType = $reflector->getReturnType();

        if ($returnType instanceof \ReflectionIntersectionType) {
            foreach ($returnType->getTypes() as $type) {
                if ($this->checkReturnType($type)){
                    $returnType = $type;
                    break;
                }
            }
        }

        if (!$this->checkReturnType($returnType)) {
            throw UnresolvableMiddlewareException::makeFrom($middleware);
        }

        if ($returnType->getName() != ResponseInterface::class ||
            is_subclass_of($returnType->getName(), ResponseInterface::class)) {
            try {
                $middleware = $this->call($middleware, $reflector->getParameters());
            } catch (\Throwable $e) {
                throw UnresolvableMiddlewareException::fromPrev($e, $middleware);
            }

            if ($middleware instanceof MiddlewareInterface) return $middleware;
            else return new RequestHandlerAdapter($middleware);
        }

        if (($count = count($parameters = $reflector->getParameters())) == 0) {
            return new CallableAdapter($middleware);
        }

        if ($count == 1 && $this->checkType($parameters[0], ContainerInterface::class)) {
            return CallableAdapter::adoptContainerParameterCallable($middleware, $this->container);
        }

        if ($this->checkType($parameters[0], ServerRequestInterface::class)) {

            if ($count == 1) {
                return new CallableAdapter($middleware);
            }

            if ($count == 2) {
                if ($this->checkType($parameters[1], RequestHandlerInterface::class)) {
                    return new CallableAdapter($middleware);
                }

                if ($this->declaresCallable($parameters[1])) {
                    return CallableAdapter::adoptSinglePassMiddleware($middleware);
                }
            }

            if ($count === 3) {
                if ($this->checkType($parameters[1], ResponseInterface::class)
                    && $this->declaresCallable($parameters[2])) {
                    return CallableAdapter::adoptDoublePassMiddleware($middleware, $this->responseFactory);
                }
            }
        }

        return CallableAdapter::adoptAttributes($middleware, $this->container, $this->invoker, $parameters);
    }

    /**
     * @throws \ReflectionException
     */
    private function getReflector(string $middleware): \ReflectionMethod
    {
        list($class, $method) = explode('::', $middleware, 2);
        return new \ReflectionMethod($class, $method);
    }

    private function checkReturnType(?\ReflectionType $type): bool
    {
        if (!$type instanceof ReflectionNamedType) return false;
        return ($typeName = $type->getName()) == MiddlewareInterface::class || $typeName == ResponseInterface::class || $typeName == RequestHandlerInterface::class
            || is_subclass_of($typeName, MiddlewareInterface::class)
            || is_subclass_of($typeName, ResponseInterface::class)
            || is_subclass_of($typeName, RequestHandlerInterface::class);
    }

    /**
     * @param ReflectionParameter $parameter
     * @param string $type
     * @return bool
     */
    private function checkType(ReflectionParameter $parameter, string $type): bool
    {
        if (!($refType = $parameter->getType()) instanceof ReflectionNamedType) {
            return false;
        }

        return Type::isInterface($refType->getName(), $type)
            || is_subclass_of($refType->getName(), $type);
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

        return array_any($types, fn($type) => $type->getName() == 'callable');

    }

    /**
     * @param callable $callable
     * @param ReflectionParameter[] $parameters
     * @return MiddlewareInterface|RequestHandlerInterface
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvocationException
     * @throws NotCallableException
     * @throws NotEnoughParametersException
     */
    private function call(callable $callable, array $parameters): MiddlewareInterface|RequestHandlerInterface
    {
        $params = [];
        foreach ($parameters as $parameter) {
            $attribute = $parameter->getAttributes(Container::class)[0] ?? null;
            if ($attribute || ($attribute = $parameter->getAttributes(Config::class)[0] ?? null) !== null) {
                $attribute = $attribute->newInstance();
                list($key, $value) = $attribute->getParameter($this->container, $parameter);
                $params[$key] = $value;
            }
        }

        return $this->invoker->call($callable, $params);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function createFromContainer(ContainerInterface $container): CallableStrategy
    {
        return new static($container, $container->get(InvokerInterface::class), $container->get(ResponseFactoryInterface::class));
    }
}
