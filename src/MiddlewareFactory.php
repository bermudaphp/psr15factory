<?php


namespace Lobster\MiddlewareFactory;


use Lobster\Type;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;


/**
 * Class MiddlewareFactory
 * @package Lobster\MiddlewareFactory
 */
final class MiddlewareFactory implements MiddlewareFactoryInterface
{
    private ContainerInterface $container;
    private ResponseFactoryInterface $responseFactory;
    private Contracts\PipelineFactory $pipelineFactory;

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
     * @inheritDoc
     */
    public function make($any): MiddlewareInterface
    {
        if(is_string($any) && $this->container->has($any))
        {
            if(is_subclass_of($middleware, MiddlewareInterface::class) ||
                is_subclass_of($middleware, RequestHandlerInterface::class))
            {
                return new Decorator\ServiceDecorator($any, $this->container);
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
                $pipeline->pipe($this->make($item));
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
                MiddlewareFactoryException::invalidReturnType($any, $returnType->getName())->throw();
            }

            if (($count = count($parameters = $method->getParameters())) == 1)
            {
                if ($this->checkType($parameters[0], ServerRequestInterface::class))
                {
                    return new Decorator\CallbackDecorator($any);
                }
            }

            if ($count == 2)
            {
                if ($this->checkType($parameters[0], ServerRequestInterface::class) &&
                    $this->checkType($parameters[1], RequestHandlerInterface::class))
                {
                     return new Decorator\CallbackDecorator($any);
                }

                if ($this->checkType($parameters[0], ServerRequestInterface::class)
                    && $parameters[1]->isCallable())
                {
                    return new Decorator\SinglePassDecorator($any);
                }
            }

            if ($count === 3)
            {
                if ($this->checkType($parameters[0], ServerRequestInterface::class) &&
                    $this->checkType($parameters[0], ResponseInterface::class)
                    && $parameters[2]->isCallable())
                {
                    return new Decorator\DoublePassDecorator($any, $this->responseFactory);
                }
            }
        }

        MiddlewareFactoryException::notCreatable($any)->throw();
    }
    
    /**
     * @inheritDoc
     */
    public function __invoke($any) : MiddlewareInterface 
    {
        return $this->make($any);
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
