<?php


namespace Bermuda\MiddlewareFactory\Decorator;


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;


/**
 * Class ArgumentDecorator
 * @package Bermuda\MiddlewareFactory\Decorator
 */
final class ArgumentDecorator implements MiddlewareInterface
{
    /**
     * @var callable
     */
    private $handler;

    /**
     * @var \ReflectionParameter[]
     */
    private array $params;

    public function __construct(callable $handler, array $params)
    {
        $this->params = $params;
        $this->handler = $handler;
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws \ReflectionException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $args = [];
        $attributes = $request->getAttributes();

        foreach ($this->params as $param)
        {
            if (array_key_exists($param->getName(), $attributes))
            {
                $args[] = $attributes[$param->getName()];
                continue;
            }
            
            if (($cls = $param->getClass()) != null)
            {
                $cls = $cls->getName();
                
                foreach ($attributes as $attribute)
                {
                    if ($attribute instanceof $cls)
                    {
                        $args[] = $attribute;
                        break;
                    }
                }
                
                continue;
            }

            if ($param->isDefaultValueAvailable())
            {
                $args[] = $param->getDefaultValue();
                continue;
            }

            if ($param->allowsNull())
            {
                $args[] = null;
                continue;
            }

            throw new \RuntimeException('Missing request attribute with name: ' . $param->name);
        }

        return call_user_func_array($this->handler, $args);
    }
}
