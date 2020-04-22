<?php


namespace Lobster\Resolver;


use Lobster\Type;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Lobster\Resolver\Decorators\LazyDecorator;
use Lobster\Pipeline\Contracts\PipelineFactory;
use Lobster\Resolver\Decorators\CallableDecorator;
use Lobster\Resolver\Decorators\RequestHandlerDecorator;


/**
 * Class Resolver
 * @package Lobster\Resolver
 */
final class Resolver implements Contracts\Resolver
{
    private ContainerInterface $container;
    private PipelineFactory $pipelineFactory;
    private ResponseFactoryInterface $responseFactory;

    /**
     * Resolver constructor.
     * @param ContainerInterface $container
     * @param PipelineFactoryInterface $pipelineFactory
     * @param ResponseFactoryInterface $responseFactory
     */
    public function __construct(
        ContainerInterface $container,
        ResponseFactoryInterface $responseFactory,
        PipelineFactory $pipelineFactory = null
    )
    {
        $this->container = $container;
        $this->responseFactory = $responseFactory;
        $this->pipelineFactory = $pipelineFactory ?? new \Lobster\Pipeline\PipelineFactory();
    }

    /**
     * @param mixed $middleware
     * @return MiddlewareInterface
     * @throws UnresolvableMiddlewareException
     */
    public function resolve($middleware): MiddlewareInterface
    {

        if($this->isLazyLoadMiddleware($middleware))
        {
            return new LazyDecorator($middleware, $this->container);
        }

        if($this->isMiddlewareInstance($middleware))
        {
            return $middleware;
        }

        if($this->isRequestHandlerInstance($middleware))
        {
            return new RequestHandlerDecorator($middleware);
        }

        if($this->isIterable($middleware))
        {
            return ($this->pipelineFactory)($middleware);
        }
        
        if($this->isCallableMiddleware($middleware))
        {

            if (is_object($middleware))
            {
                $parameters = (new \ReflectionObject($middleware))
                    ->getMethod('__invoke')->getParameters();
            }

            else
            {
                $parameters = (new \ReflectionFunction($middleware))
                    ->getParameters();
            }

            if (($count = count($parameters)) == 1)
            {
                if ($this->checkType($parameters[0], ServerRequestInterface::class))
                {
                    return CallableDecorator::decorate($middleware);
                }
            }

            if ($count == 2)
            {
                if ($this->checkType($parameters[0], ServerRequestInterface::class) &&
                    $this->checkType($parameters[1], RequestHandlerInterface::class))
                {
                    return CallableDecorator::decorate($middleware);
                }

                if ($this->checkType($parameters[0], ServerRequestInterface::class)
                    && $parameters[1]->isCallable())
                {
                    return CallableDecorator::singlePass($middleware);
                }
            }

            if ($count === 3)
            {
                if ($this->checkType($parameters[0], ServerRequestInterface::class) &&
                    $this->checkType($parameters[0], ResponseInterface::class)
                    && $parameters[2]->isCallable())
                {
                    CallableDecorator::doublePass($middleware, $this->responseFactory);
                }
            }

        }

        UnresolvableMiddlewareException::throw($middleware);
    }

    /**
     * @param \ReflectionParameter $parameter
     * @param string $type
     * @return bool
     */
    private function checkType(\ReflectionParameter $parameter, string $type) : bool
    {
        if (!($refType = $parameter->getType()) instanceof \ReflectionNamedType)
        {
            return false;
        }

        return Type::isInterface($refType->getName(), $type);
    }

    /**
     * @param \ReflectionType|null $type
     * @return bool
     */
    private function isServerRequestType(?\ReflectionType $type) : bool
    {
        return $type instanceof \ReflectionNamedType
            && Type::isInterface($type->getName(), ServerRequestInterface::class);
    }

    /**
     * @param \ReflectionType|null $type
     * @return bool
     */
    private function isResponseType(?\ReflectionType $type) : bool
    {
        return $type instanceof \ReflectionNamedType
            && Type::isInterface($type->getName(), ServerRequestInterface::class);
    }

    /**
     * @param \ReflectionType|null $type
     * @return bool
     */
    private function isRequestHandlerType(?\ReflectionType $type) : bool
    {
        return $type instanceof \ReflectionNamedType
            && is_subclass_of($type->getName(), RequestHandlerInterface::class);
    }

    /**
     * @param $middleware
     * @return bool
     */
    private function isMiddlewareInstance($middleware) : bool
    {
        return $middleware instanceof MiddlewareInterface;
    }

    /**
     * @param $middleware
     * @return bool
     */
    private function isLazyLoadMiddleware($middleware) : bool
    {
        return is_string($middleware) && $this->container->has($middleware)
            && (is_subclass_of($middleware, MiddlewareInterface::class) ||
                is_subclass_of($middleware, RequestHandlerInterface::class));
    }

    /**
     * @param $middleware
     * @return bool
     */
    private function isIterable($middleware) : bool
    {
        return is_iterable($middleware);
    }

    /**
     * @param $middleware
     * @return bool
     */
    private function isRequestHandlerInstance($middleware) : bool
    {
        return $middleware instanceof RequestHandlerInterface;
    }

    /**
     * @param $middleware
     * @return bool
     */
    private function isCallableMiddleware($middleware) : bool
    {
        return is_callable($middleware);
    }
}
