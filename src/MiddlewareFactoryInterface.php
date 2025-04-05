<?php

namespace Bermuda\MiddlewareFactory;

use Psr\Http\Server\MiddlewareInterface;

interface MiddlewareFactoryInterface
{
    public function makeMiddleware(mixed $any): MiddlewareInterface ;
}
