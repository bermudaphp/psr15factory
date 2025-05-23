<?php

namespace Bermuda\MiddlewareFactory\Resolver;

use Bermuda\MiddlewareFactory\Attribute\MapQueryParameter;
use Bermuda\MiddlewareFactory\Attribute\MapQueryString;
use Bermuda\MiddlewareFactory\Attribute\MapRequestPaylod;
use Bermuda\ParameterResolver\ParameterResolutionException;
use Bermuda\ParameterResolver\ParameterResolverInterface;
use Bermuda\ParameterResolver\ResolverException;
use Bermuda\DI\FactoryInterface;
use http\Env\Request;
use Psr\Http\Message\ServerRequestInterface;

use Bermuda\Reflection\Reflection;
use function Bermuda\Stdlib\to_array;

/**
 * RequestMapperResolver is responsible for mapping data from a PSR-7 request to a method's parameters.
 *
 * This resolver scans the parameter for one of the following custom mapping attributes:
 * - MapQueryParameter
 * - MapQueryString
 * - MapRequestPaylod
 *
 * Based on the attribute found, it extracts the corresponding data from the request (e.g., from
 * query parameters, the parsed body, or query string) and either:
 * - directly assigns the data to an array parameter, or
 * - creates an object (if the parameter is a class) via a factory, merging request attributes with
 *   the mapped data.
 */
final class RequestMapperResolver implements ParameterResolverInterface
{
    /**
     * Constructor.
     *
     * @param FactoryInterface $factory A factory used to create objects with dependency injection.
     */
    public function __construct(
        private readonly FactoryInterface $factory
    ) {}

    /**
     * Resolves a parameter value based on custom mapping attributes from the request.
     *
     * Resolution process:
     *   1. Retrieve a custom mapping attribute (MapQueryParameter, MapQueryString, or MapRequestPaylod)
     *      defined on the parameter.
     *   2. Extract the PSR-7 request instance from the provided parameters.
     *   3. Use the attribute and the parameter's name to extract the relevant data from the request.
     *   4. If the parameter type is 'array', return the extracted data, keyed by the parameter's name.
     *   5. If the parameter is a class, attempt to instantiate it using the factory by merging the request
     *      attributes with the mapped data.
     *   6. Return an array containing the parameter position and the resolved value.
     *
     * @param \ReflectionParameter $parameter           The parameter to resolve.
     * @param array                $providedParameters  An array of parameters which must include the request instance.
     * @param array                $resolvedParameters    Previously resolved parameters (unused here).
     *
     * @return array{0: int, 1: mixed}|null Returns an array [position, resolved value] if successful; otherwise, null.
     *
     * @throws ParameterResolutionException When object creation via the factory fails or required query parameters are absent.
     */
    public function resolve(\ReflectionParameter $parameter, array $providedParameters = [], array $resolvedParameters = []): ?array
    {
        if (($attribute = $this->getAttribute($parameter)) !== null) {

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

            $data = $this->extractData($attribute, $request, $parameter->getName());

            if ($parameter->getType()?->getName() === 'array') {
                return [$parameter->getPosition(), $data];
            }

            if ($parameter->getType()?->getName() && class_exists($parameter->getType()->getName())) {
                try {
                    $entry = $this->factory->make(
                        $parameter->getType()->getName(),
                        array_merge($request->getAttributes(), $data)
                    );
                } catch (\Throwable $previous) {
                    throw ParameterResolutionException::createFromPrev(
                        $parameter, $providedParameters, $resolvedParameters, $previous
                    );
                }

                return [$parameter->getPosition(), $entry];
            }
        }

        return null;
    }

    /**
     * Extracts data from the request based on the provided mapping attribute.
     *
     * This method uses a match expression to determine the extraction strategy:
     * - If the attribute is a MapQueryParameter, it calls extractQueryParameter().
     * - If the attribute is a MapRequestPaylod, it converts the parsed body to an array and remaps its keys.
     * - If the attribute is a MapQueryString, it remaps the query parameters according to the provided mapping.
     * - Otherwise, it returns an empty array.
     *
     * @param object                 $attribute The mapping attribute instance.
     * @param ServerRequestInterface $request   The PSR-7 request.
     * @param string                 $paramName The name of the parameter being resolved.
     *
     * @return array The extracted data as an associative array.
     */
    private function extractData(object $attribute, ServerRequestInterface $request, string $paramName): array
    {
        return match (true) {
            $attribute instanceof MapQueryParameter => $this->extractQueryParameter($request, $attribute, $paramName),
            $attribute instanceof MapRequestPaylod => $this->map(to_array($request->getParsedBody()), $attribute->map),
            $attribute instanceof MapQueryString => $this->map($request->getQueryParams(), $attribute->map),
            default => []
        };
    }

    /**
     * Extracts a query parameter from the request based on a MapQueryParameter attribute.
     *
     * Checks whether the query parameters contain the specified key (from the attribute or the parameter name)
     * and returns its value. Throws an exception if the required query parameter is missing.
     *
     * @param ServerRequestInterface $request   The PSR-7 request from which to extract the query parameter.
     * @param MapQueryParameter      $attribute The attribute defining the query parameter mapping.
     * @param string                 $paramName The default parameter name to use if the attribute's name is not provided.
     *
     * @return array Returns an associative array with the parameter name as the key and the extracted value.
     *
     * @throws \OutOfBoundsException If the query parameter is missing from the request.
     */
    private function extractQueryParameter(ServerRequestInterface $request, MapQueryParameter $attribute, string $paramName): array
    {
        $queryParams = $request->getQueryParams();

        if (!array_key_exists($attribute->name ?? $paramName, $queryParams)) {
            throw new \OutOfBoundsException("Query parameter '{$attribute->name}' is missing.");
        }

        return [$paramName => $queryParams[$attribute->name ?? $paramName]];
    }

    /**
     * Remaps the keys in the provided data array according to the given mapping.
     *
     * For each mapping rule, if the source key exists in the data array, its value is reassigned to the
     * destination key as specified, and the source key is removed.
     *
     * @param array $data The original data array.
     * @param array $map  An associative array representing the mapping (source key => target key).
     *
     * @return array The data array after applying the key remapping.
     */
    private function map(array $data, array $map): array
    {
        foreach ($map as $from => $to) {
            if (isset($data[$from]) || array_key_exists($from, $data)) {
                $data[$to] = $data[$from];
                unset($data[$from]);
            }
        }

        return $data;
    }

    /**
     * Retrieves the first applicable mapping attribute for the given parameter.
     *
     * Iterates over the supported mapping attribute classes: MapQueryParameter, MapQueryString,
     * and MapRequestPaylod. Returns the first attribute found, or null if none exist.
     *
     * @param \ReflectionParameter $parameter The parameter to inspect for mapping attributes.
     *
     * @return object|null The mapping attribute instance, or null if no attribute is found.
     */
    private function getAttribute(\ReflectionParameter $parameter): ?object
    {
        foreach ([MapQueryParameter::class, MapQueryString::class, MapRequestPaylod::class] as $cls) {
            $attribute = Reflection::getFirstMetadata($parameter, $cls);
            if ($attribute) return $attribute;
        }

        return null;
    }
}
