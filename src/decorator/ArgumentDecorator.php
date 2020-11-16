<?php


namespace Bermuda\MiddlewareFactory\Decorator;


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;


/**
 * Class ArgumentDecorator
 * @package Bermuda\MiddlewareFactory\Decorator
 */
final class ArgumentDecorator implements RequestHandlerInterface
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
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
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
