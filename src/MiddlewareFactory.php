<?php

namespace Bermuda\MiddlewareFactory;

use Bermuda\CheckType\Type;
use Bermuda\Pipeline\PipelineFactory;
use Bermuda\Pipeline\PipelineFactoryInterface;
use ParseError;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionObject;
use ReflectionParameter;
use Throwable;

final class MiddlewareFactory implements MiddlewareFactoryInterface
{
    public const separator = '@';
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly PipelineFactoryInterface $pipelineFactory = new PipelineFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function __invoke($any): MiddlewareInterface
    {
        return $this->make($any);
    }

    /**
     * @param ContainerInterface $container
     * @return static
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function fromContainer(ContainerInterface $container): self
    {
        return new self(
            $container, $container->get(ResponseFactoryInterface::class), 
            $container->has(PipelineFactoryInterface::class) ? 
            $container->get(PipelineFactoryInterface::class) 
            : new PipelineFactory()
       );
    }

    /**
     * @inheritDoc
     */
    public function make($any): MiddlewareInterface
    {
        if (is_string($any)) {
            if (($has = $this->container->has($any)) && is_subclass_of($any, MiddlewareInterface::class)) {
                return $this->getService($any);
            }

            if ($has && is_subclass_of($any, RequestHandlerInterface::class)) {
                return new Decorator\RequestHandlerDecorator($this->container->get($any));
            }

            if (str_contains($any, self::separator) !== false) {
                list($serviceID, $method) = explode(self::separator, $any, 2);

                if ($this->container->has($serviceID)) {
                    if (!method_exists($service = $this->getService($serviceID), $method)) {
                        goto end;
                    }
                    
                    $any = [$service, $method];
                    goto callback;
                }
            }

            if ($has && method_exists($any, '__invoke')){
                $any = $this->getService($any);
                goto invokable;
            }

            if (is_callable($any)) {
                goto str_callback;
            }

            goto end;
        }

        if ($any instanceof MiddlewareInterface) {
            return $any;
        }

        if ($any instanceof RequestHandlerInterface) {
            return new Decorator\RequestHandlerDecorator($any);
        }

        if (is_callable($any)) {
            if (is_object($any)) {
                invokable:
                $method = (new ReflectionObject($any))
                    ->getMethod('__invoke');
            } elseif (is_array($any)) {
                callback:
                $method = new ReflectionMethod($any[0], $any[1]);
            } elseif (str_contains($any, '::') !== false) {
                $method = new ReflectionMethod($any);
            } else {
                str_callback:
                $method = new ReflectionFunction($any);
            }

            $returnType = $method->getReturnType();

            if (!$returnType instanceof ReflectionNamedType || !$this->checkReturnType($returnType->getName())) {
                throw UnresolvableMiddlewareException::invalidReturnType($any, $returnType != null ? $returnType->getName() : 'void');
            }

            if ($returnType->getName() == MiddlewareInterface::class ||
                is_subclass_of($returnType->getName(), MiddlewareInterface::class)) {
                return $any($this->container);
            }

            if (($count = count($parameters = $method->getParameters())) == 0) {
                return Decorator\CallbackDecorator::decorateEmptyArgsCallable($any);
            }

            if ($count == 1 && $this->checkType($parameters[0], ContainerInterface::class)) {
                return Decorator\CallbackDecorator::decorateContainerArgCallable($any, $this->container);
            }

            if ($this->checkType($parameters[0], ServerRequestInterface::class)) {
                
                if ($count == 1) {
                    return new Decorator\CallbackDecorator($any);
                }

                if ($count == 2) {
                    if ($this->checkType($parameters[1], RequestHandlerInterface::class)) {
                        return new Decorator\CallbackDecorator($any);
                    }

                    if ($this->declaresCallable($parameters[1])) {
                        return new Decorator\SinglePassDecorator($any);
                    }
                }

                if ($count === 3) {
                    if ($this->checkType($parameters[1], ResponseInterface::class)
                        && $this->declaresCallable($parameters[2])) {
                        return new Decorator\DoublePassDecorator($any, $this->responseFactory);
                    }
                }
            }

            return new Decorator\ArgumentDecorator($any, $parameters);
        }

        if (is_iterable($any)) {
            $pipeline = $this->pipelineFactory->make();
            foreach ($any as $m) $pipeline->pipe($this->make($m));
            return $pipeline;
        }

        end:
        throw UnresolvableMiddlewareException::notCreatable($any);
    }

    /**
     * @param string $id
     * @return object
     * @throws UnresolvableMiddlewareException
     */
    private function getService(string $serviceID): object
    {
        try {
            return $this->container->get($serviceID);
        } catch (ContainerExceptionInterface|NotFoundExceptionInterface $e) {
            throw UnresolvableMiddlewareException::fromPrevious($e, $serviceID);
        }
    }

    /**
     * @param string $type
     * @return bool
     */
    private function checkReturnType(string $type): bool
    {
        return $type == MiddlewareInterface::class || $type == ResponseInterface::class
            || is_subclass_of($type, MiddlewareInterface::class)
            || is_subclass_of($type, ResponseInterface::class);
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

        foreach ($types as $type) {
            if ($type->getName() == 'callable') {
                return true;
            }
        }

        return false;
    }
}
