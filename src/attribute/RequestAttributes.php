<?php

namespace MiddlewareFactory\attribute;

use Psr\Http\Message\ServerRequestInterface;

#[\Attribute(\Attribute::TARGET_PARAMETER)] class RequestAttributes
{
    public function __construct(
        public readonly string $name,
    ) {
    }

    public function getParameter(ServerRequestInterface $request, \ReflectionParameter $parameter): array
    {
        if (!isset($request->getAttributes()[$this->name])) {
            if ($parameter->isDefaultValueAvailable()) {
                return [$parameter->getName(), $parameter->getDefaultValue()];
            }

            throw new \RuntimeException('Missed request attribute "' . $this->name . '"');
        }

        return [$parameter->getName(), $request->getAttributes()[$this->name]];
    }
}