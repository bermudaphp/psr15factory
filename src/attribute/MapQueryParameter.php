<?php

namespace Bermuda\MiddlewareFactory\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]class MapQueryParameter
{
    public function __construct(
        public ?string $name = null,
    ) {
    }
}