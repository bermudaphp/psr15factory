<?php

namespace Bermuda\MiddlewareFactory\Attribute;

use Psr\Http\Message\ServerRequestInterface;

#[\Attribute(\Attribute::TARGET_PARAMETER)] class RequestAttribute
{
    public function __construct(
        public readonly ?string $name=null,
    ) {
    }

    public function getParameter(ServerRequestInterface $request, \ReflectionParameter $parameter): array
    {
        if (!isset($request->getAttributes()[$name = $this->name ?? $parameter->getName()])) {
            if ($parameter->isDefaultValueAvailable()) {
                return [$parameter->getName(), $parameter->getDefaultValue()];
            }

            throw new \RuntimeException('Missed request attribute "' . $name . '"');
        }

        return [$parameter->getName(), $request->getAttributes()[$name]];
    }
}
