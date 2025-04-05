<?php

namespace Bermuda\MiddlewareFactory\Strategy;

use Psr\Http\Server\MiddlewareInterface;

interface StrategyInterface
{
    public function makeMiddleware(mixed $middleware):? MiddlewareInterface;
}
