<?php

namespace MiddlewareFactory\strategy;

use Psr\Http\Server\MiddlewareInterface;

interface StrategyInterface
{
    public function makeMiddleware(mixed $middleware):? MiddlewareInterface;
}