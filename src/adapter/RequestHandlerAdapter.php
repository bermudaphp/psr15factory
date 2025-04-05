<?php

namespace Bermuda\MiddlewareFactory\Adapter;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RequestHandlerAdapter implements MiddlewareInterface, RequestHandlerInterface
{
    public const string FALLBACK_HANDLER_ATTRIBUTES_KEY = "Bermuda\MiddlewareFactory\Adapter:fallback";
    public function __construct(
        private readonly RequestHandlerInterface $handler,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->handler->handle($request->withAttribute(self::FALLBACK_HANDLER_ATTRIBUTES_KEY, $handler));
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handler->handle($request);
    }
}
