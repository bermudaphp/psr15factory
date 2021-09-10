<?php

namespace Bermuda\MiddlewareFactory\Decorator;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SinglePassDecorator extends CallbackDecorator
{
    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return ($this->callback)($request, static function (ServerRequestInterface $request) use ($handler): ResponseInterface {
            return $handler->handle($request);
        });
    }
}
