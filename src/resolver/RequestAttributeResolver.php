<?php

namespace Bermuda\MiddlewareFactory\Resolver;

use Bermuda\MiddlewareFactory\Attribute\RequestAttribute;
use Bermuda\ParameterResolver\Resolver\ParameterResolverInterface;
use Bermuda\Reflection\TypeMatcher;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionParameter;

final class RequestAttributeResolver implements ParameterResolverInterface
{
    public const string REQUEST_PARAMETER_KEY = ServerRequestInterface::class;

    public function resolve(ReflectionParameter $parameter, array $params = []): ?array
    {
        if (!isset($params[self::REQUEST_PARAMETER_KEY])) {
            throw new ResolverException('Missing $params['.self::REQUEST_PARAMETER_KEY.'] parameter');
        }

        if (!$params[self::REQUEST_PARAMETER_KEY] instanceof ServerRequestInterface) {
            throw new ResolverException('$params['.self::REQUEST_PARAMETER_KEY.'] must be instance of '. ServerRequestInterface::class);
        }

        $request = $params[self::REQUEST_PARAMETER_KEY];

        $attribute = $parameter->getAttributes(RequestAttribute::class)[0] ?? null;
        if (!$attribute) return null;

        $name = $attribute->getArguments()[0] ?? $parameter->getName();
        if (!isset($request->getAttributes()[$name])) {
            throw new ResolverException('Missing request attribute: ' . $name);
        }

        $this->checkParamType($parameter, $request->getAttributes()[$name]);

        return [$name, $request->getAttributes()[$name]];
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
