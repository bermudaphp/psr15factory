<?php


namespace Bermuda\MiddlewareFactory;


use Bermuda\CheckType\Type;
use Bermuda\Pipeline\PipelineFactory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Bermuda\Pipeline\PipelineFactoryInterface;


use function Bermuda\str_contains;


/**
 * Class MiddlewareFactory
 * @package Bermuda\MiddlewareFactory
 */
final class MiddlewareFactory implements MiddlewareFactoryInterface
{
    private ContainerInterface $container;
    private ResponseFactoryInterface $responseFactory;
    private PipelineFactoryInterface $pipelineFactory;

    public const separator = '@';

    public function __construct(
        ContainerInterface $container,
        ResponseFactoryInterface $responseFactory,
        PipelineFactoryInterface $pipelineFactory = null
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
        if (is_string($any))
        {
            if ($this->container->has($any) && is_subclass_of($any, MiddlewareInterface::class))
            {
                return $this->service($any);
            }

            if ($this->container->has($any) && is_subclass_of($any, RequestHandlerInterface::class))
            {
                return new Decorator\RequestHandlerDecorator($this->container->get($any));
            }

            if (str_contains($any, self::separator) !== false)
            {
                list($service, $method) = explode(self::separator, $any, 2);

                if ($this->container->has($service) && method_exists($service, $method))
                {
                    $any = [$this->service($service), $method];
                    goto callback;
                }
            }

            if (is_callable($any))
            {
                goto str_callback;
            }

            goto end;
        }

        if ($any instanceof MiddlewareInterface)
        {
            return $any;
        }

        if ($any instanceof RequestHandlerInterface)
        {
            return new Decorator\RequestHandlerDecorator($any);
        }

        if (is_callable($any))
        {
            if (is_object($any))
            {
                $method = (new \ReflectionObject($any))
                    ->getMethod('__invoke');
            }

            elseif (is_array($any))
            {
                callback:
                $method = new \ReflectionMethod($any[0], $any[1]);
            }

            else
            {
                if (str_contains($any, '::') !== false)
                {
                    $method = new \ReflectionMethod($any);
                }

                else
                {
                    str_callback:
                    $method = new \ReflectionFunction($any);
                }
            }

            $returnType = $method->getReturnType();

            if (!$returnType instanceof \ReflectionNamedType || ($returnType->getName() != 'Psr\Http\Message\ResponseInterface' &&
                    $returnType->getName() != MiddlewareInterface::class))
            {
                MiddlewareFactoryException::invalidReturnType($any, 'void')->throw();
            }

            if ($returnType->getName() == MiddlewareInterface::class)
            {
                try
                {
                    return $any($this->container);
                }

                catch (\Throwable $e)
                {
                    MiddlewareFactoryException::fromPrevios($e, $any)->throw();
                }
            }

            if (($count = count($parameters = $method->getParameters())) == 0)
            {
                return new Decorator\CallbackDecorator($any);
            }
            
            if ($this->checkType($parameters[0], ServerRequestInterface::class))
            {
                if ($count == 1)
                {
                    return new Decorator\CallbackDecorator($any);
                }

                if ($count == 2)
                {
                    if ($this->checkType($parameters[1], RequestHandlerInterface::class))
                    {
                        return new Decorator\CallbackDecorator($any);
                    }

                    if ($parameters[1]->isCallable())
                    {
                        return new Decorator\SinglePassDecorator($any);
                    }
                }

                if ($count === 3)
                {
                    if ($this->checkType($parameters[1], ResponseInterface::class)
                        && $parameters[2]->isCallable())
                    {
                        return new Decorator\DoublePassDecorator($any, $this->responseFactory);
                    }
                }
            }
            
            return new Decorator\ArgumentDecorator($any, $parameters);
        }

        if (is_iterable($any))
        {
            $pipeline = $this->pipelineFactory->make();

            foreach ($any as $item)
            {
                $pipeline->pipe($this->make($item));
            }

            return $pipeline;
        }

        end:
        MiddlewareFactoryException::notCreatable($any)->throw();
    }

    /**
     * @inheritDoc
     */
    public function __invoke($any): MiddlewareInterface
    {
        return $this->make($any);
    }

    /**
     * @param string $service
     * @return object
     * @throws MiddlewareFactoryException
     */
    private function service(string $service): object
    {
        try
        {
            return $this->container->get($service);
        }

        catch (\Throwable $e)
        {
            MiddlewareFactoryException::fromPrevios($e, $service)->throw();
        }
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
