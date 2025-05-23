<?php

namespace Bermuda\MiddlewareFactory\Resolver;

use Bermuda\MiddlewareFactory\Attribute\RequestAttribute;
use Bermuda\ParameterResolver\ParameterResolutionException;
use Bermuda\ParameterResolver\ParameterResolverInterface;
use Bermuda\ParameterResolver\ResolverException;
use Bermuda\Reflection\TypeMatcher;
use Psr\Http\Message\ServerRequestInterface;
use Bermuda\Reflection\Reflection;

/**
 * Class RequestAttributeResolver
 *
 * This parameter resolver extracts attributes from a PSR-7 server request and binds them
 * to function or method parameters. It does so by checking for a RequestAttribute annotation on the
 * method/function parameter and then using the specified attribute name (or defaulting to the parameter’s name)
 * to retrieve the corresponding attribute value from the request.
 *
 * The resolution process works as follows:
 *   1. It retrieves the first RequestAttribute metadata for the parameter.
 *   2. It extracts the request instance from the provided parameters array.
 *   3. It determines the attribute name: if the RequestAttribute annotation explicitly defines a name,
 *      that value is used; otherwise, the parameter's own name is used.
 *   4. It validates that the PSR-7 request contains this attribute. If not, a ParameterResolutionException is thrown.
 *   5. Finally, it returns an array containing the parameter’s position and the resolved attribute value.
 *
 * @implements ParameterResolverInterface
 */
final class RequestAttributeResolver implements ParameterResolverInterface
{
    /**
     * Resolves a request attribute and binds it to the corresponding function or method parameter.
     *
     * This method searches for a RequestAttribute annotation on the given parameter and uses it to extract the
     * corresponding attribute from a PSR-7 request. The request instance is expected to be among the provided parameters.
     * If the attribute is not found in the request, a ParameterResolutionException is thrown.
     *
     * @param \ReflectionParameter $parameter          The reflection of the parameter to be resolved.
     * @param array                $providedParameters An array of provided parameters that should include a PSR-7 request.
     * @param array                $resolvedParameters An array of parameters that have been resolved so far (not used here).
     *
     * @return array{0: int, 1: mixed}|null Returns a two-element array where key "0" is the parameter position and key "1" is the resolved
     *                    request attribute value, or null if the RequestAttribute annotation is not present.
     *
     * @throws ParameterResolutionException If the specified request attribute is missing.
     */
    public function resolve(\ReflectionParameter $parameter, array $providedParameters = [], array $resolvedParameters = []): ?array
    {
        $attribute = Reflection::getFirstMetadata($parameter, RequestAttribute::class);
        if (!$attribute) return null;

        $request = null;
        if (RequestParameter::has($providedParameters)) $request = RequestParameter::get($providedParameters);

        if (!$request) {
            throw new ParameterResolutionException(
                $parameter,
                $providedParameters,
                $resolvedParameters,
                "No PSR-7 request instance found in provided parameters. \$providedParameters['".RequestParameter::KEY.".'] must be instanceof " . ServerRequestInterface::class
            );
        }

        $request = RequestParameter::get($providedParameters);
        $name = $attribute->name ?? $parameter->getName();

        if (!isset($request->getAttributes()[$name]) || !array_key_exists($name, $request->getAttributes())) {
            throw new ParameterResolutionException(
                $parameter,
                $providedParameters,
                $resolvedParameters,
                "The required request attribute [$name] is not set for the current request"
            );
        }

        return [$parameter->getPosition(), $request->getAttributes()[$name]];
    }
}
