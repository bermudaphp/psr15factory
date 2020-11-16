<?php


namespace Bermuda\MiddlewareFactory\Decorator;


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;


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

        foreach ($this->params as $param)
        {
            if ($param->isOptional())
            {
                $args[] = $request->getAttribute($param->getName(), $param->getDefaultValue());
                continue;
            }

            $args[] = $request->getAttribute($param->getName(), null);
        }

        return call_user_func_array($this->handler, $args);
    }
}
