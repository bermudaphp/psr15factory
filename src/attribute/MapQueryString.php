<?php

namespace Bermuda\MiddlewareFactory\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]class MapQueryString
{
    public function __construct(public readonly array $map = [])
    {
    }
}