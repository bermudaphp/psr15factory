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
use Lobster\Resolver\Decorators\CallableDecorator;
use Lobster\Resolver\Decorators\RequestHandlerDecorator;


/**
 * Class Resolver
 * @package Lobster\Resolver
 */
final class Resolver implements Contracts\Resolver
{
    private ContainerInterface $container;
    private ResponseFactoryInterface $responseFactory;
    private Contracts\PipelineFactory $pipelineFactory;

    /**
     * Resolver constructor.
     * @param ContainerInterface $container
     * @param PipelineFactoryInterface $pipelineFactory
     * @param ResponseFactoryInterface $responseFactory
     */
    public function __construct(
        ContainerInterface $container,
        ResponseFactoryInterface $responseFactory,
        Contracts\PipelineFactory $pipelineFactory = null
    )
    {
        $this->container = $container;
        $this->responseFactory = $responseFactory;
        $this->pipelineFactory = $pipelineFactory ?? new PipelineFactory();
    }

    /**
     * @param mixed $any
     * @return MiddlewareInterface
     * @throws UnresolvableMiddlewareException
     */
    public function resolve($any): MiddlewareInterface
    {
        if(is_string($any) && $this->container->has($any))
        {
            if(is_subclass_of($middleware, MiddlewareInterface::class) ||
                is_subclass_of($middleware, RequestHandlerInterface::class))
            {
                return new ServiceDecorator($any, $this->container);
            }
            
            $any = $this->container->get($any);
        }
           
        if($any instanceof MiddlewareInterface)
        {
            return $any;
        }
        
        if($any instanceof RequestHandlerInterface)
        {
            return new RequestHandlerDecorator($any);
        }

        if(is_iterable($any))
        {
            $pipeline = ($this->pipelineFactory)();
            
            foreach ($any as $item)
            {
                $pipeline->pipe($this->resolve($item));
            }
            
            return $pipeline;
        }
        
        if(is_callable($any))
        {
            if (is_object($any))
            {
                $method = (new \ReflectionObject($any))
                    ->getMethod('__invoke');
            }
            
            elseif(is_array($any))
            {
                $method = new \ReflectionMethod($any[0], $any[1]);
            }

            else
            {
                if(str_pos($any, '::') !== false)
                {
                    $method = new \ReflectionMethod($any);
                }
                
                else
                {
                   $method = new \ReflectionFunction($any);
                }
            }
            
            if(!($returnType = $method->getReturnType()) instanceof \ReflectionNamedType
               && $returnType->getName() != 'Psr\Http\Message\ResponseInterface')
            {
                ExceptionFactory::invalidReturnType($returnType)->throw();
            }

            if (($count = count($parameters = $method->getParameters())) == 1)
            {
                if ($this->checkType($parameters[0], ServerRequestInterface::class))
                {
                    return new CallableDecorator($any);
                }
            }

            if ($count == 2)
            {
                if ($this->checkType($parameters[0], ServerRequestInterface::class) &&
                    $this->checkType($parameters[1], RequestHandlerInterface::class))
                {
                     return new CallableDecorator($any);
                }

                if ($this->checkType($parameters[0], ServerRequestInterface::class)
                    && $parameters[1]->isCallable())
                {
                    return new SinglePassDecorator($any);
                }
            }

            if ($count === 3)
            {
                if ($this->checkType($parameters[0], ServerRequestInterface::class) &&
                    $this->checkType($parameters[0], ResponseInterface::class)
                    && $parameters[2]->isCallable())
                {
                    return new DoublePassDecorator($any, $this->responseFactory);
                }
            }
        }

        ExceptionFactory::unresolvable($any)->throw();
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
}
