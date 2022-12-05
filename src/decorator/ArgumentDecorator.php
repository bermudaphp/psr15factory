<?php

namespace Bermuda\MiddlewareFactory\Decorator;

use RuntimeException;
use ReflectionParameter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ArgumentDecorator implements MiddlewareInterface
{
    /**
     * @var callable
     */
    private $handler;

    /**
     * @var ReflectionParameter[]
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
     * @throws RuntimeException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $args = [];
        $attributes = $request->getAttributes();

        foreach ($this->params as $param) {
            if ($param->getType()?->getName() === ServerRequestInterface::class) {
                $args[] = $request;
                continue;
            }

            if (array_key_exists($param->getName(), $attributes)) {
                $args[] = $attributes[$param->getName()];
                continue;
            }

            if (!$param->getType()?->isBuiltin()) {
                $cls = $param->getType()->getName();
                foreach ($attributes as $attribute) {
                    if ($attribute instanceof $cls) {
                        $args[] = $attribute;
                        break;
                    }
                }

                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            if ($param->allowsNull()) {
                $args[] = null;
                continue;
            }

            throw new RuntimeException("Request attribute [$param->name] for route handler is missing");
        }

        return call_user_func_array($this->handler, $args);
    }
}
