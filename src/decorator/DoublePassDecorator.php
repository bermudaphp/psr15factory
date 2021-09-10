<?php

namespace Bermuda\MiddlewareFactory\Decorator;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class DoublePassDecorator extends CallbackDecorator
{
    private ResponseFactoryInterface $factory;

    public function __construct(callable $callback, ResponseFactoryInterface $factory)
    {
        $this->factory = $factory;
        parent::__construct($callback);
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return ($this->callback)($request, $this->factory->createResponse(), static fn(ServerRequestInterface $request): ResponseInterface => $handler->handle($request));
    }
}
