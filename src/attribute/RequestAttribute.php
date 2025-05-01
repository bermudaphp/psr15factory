<?php

namespace Bermuda\MiddlewareFactory\Attribute;

use Psr\Http\Message\ServerRequestInterface;

#[\Attribute(\Attribute::TARGET_PARAMETER)] class RequestAttribute
{
    public function __construct(
        public readonly ?string $name=null,
    ) {
    }
}
