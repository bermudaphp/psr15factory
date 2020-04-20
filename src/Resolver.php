<?php


namespace Lobster\Resolver;



use Psr\Container\ContainerInterface;
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

        if($this->isLazyLoadMiddleware($middleware)){
            return new LazyDecorator($middleware, $this->container);
        }

        if($this->isMiddlewareInstance($middleware)){
            return $middleware;
        }

        if($this->isRequestHandlerInstance($middleware)){
            return new RequestHandlerDecorator($middleware);
        }

        if($this->isIterable($middleware)){
            return ($this->pipelineFactory)($middleware);
        }
        
        if($this->isCallableMiddleware($middleware)){
            
            $reflector = new \ReflectionObject($middleware);

            $method = $reflector->getMethod('__invoke');

            if (($count = count($parameters = $method->getParameters())) == 1)
            {
                return CallableDecorator::route($middleware);
            }

            return $count === 2 && $parameters[1]->isCallable() ?
                CallableDecorator::singlePass($middleware) :
                CallableDecorator::doublePass($middleware, $this->responseFactory);
        }

        UnresolvableMiddlewareException::throw($middleware);
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
        return is_object($middleware) && is_callable($middleware);
    }
}
