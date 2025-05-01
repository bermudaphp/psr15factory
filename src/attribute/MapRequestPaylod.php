<?php

namespace Bermuda\MiddlewareFactory\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]class MapRequestPaylod
{
    public function __construct(public readonly array $map = [])
    {
    }
}