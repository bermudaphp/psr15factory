<?php

namespace Bermuda\MiddlewareFactory\Resolver;

use Bermuda\MiddlewareFactory\Attribute\FallbackRequestHandler;
use Bermuda\Reflection\TypeMatcher;
use Psr\Http\Message\ServerRequestInterface;
use Bermuda\ParameterResolver\Resolver\ParameterResolverInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class FallbackRequestHandlerResolver implements ParameterResolverInterface
{
    public const string FALLBACK_HANDLER_PARAMETER_KEY = 'Bermuda\MiddlewareFactory\Resolver:fallbackHandler';

    public function resolve(ReflectionParameter $parameter, array $params = []): ?array
    {
        if (!isset($params[self::FALLBACK_HANDLER_PARAMETER_KEY])) {
            throw new ResolverException('Missing $params['.self::FALLBACK_HANDLER_PARAMETER_KEY.'] parameter');
        }

        if (!$params[self::REQUEST_PARAMETER_KEY] instanceof RequestHandlerInterface) {
            throw new ResolverException('$params['.self::FALLBACK_HANDLER_PARAMETER_KEY.'] must be instance of '. RequestHandlerInterface::class);
        }

        $handler = $params[self::REQUEST_PARAMETER_KEY];

        $attribute = $parameter->getAttributes(FallbackRequestHandler::class)[0] ?? null;
        if (!$attribute) return null;

        $this->checkParamType($parameter, $handler);

        return [$name, $handler];
    }

    private function checkParamType(ReflectionParameter $parameter, mixed $entry)
    {
        if ($parameter->getType() !== null) {
            $matcher = new TypeMatcher();
            if (!$matcher->match($parameter->getType(), $entry)) {
                throw ResolverException::createForParametrType($parameter, $entry);
            }
        }
    }
}
