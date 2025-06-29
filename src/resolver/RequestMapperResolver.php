<?php

namespace Bermuda\MiddlewareFactory\Resolver;

use Bermuda\MiddlewareFactory\Attribute\MapQueryParameter;
use Bermuda\MiddlewareFactory\Attribute\MapQueryString;
use Bermuda\MiddlewareFactory\Attribute\MapRequestPayload;
use Bermuda\ParameterResolver\ParameterResolutionException;
use Bermuda\ParameterResolver\ParameterResolverInterface;
use Bermuda\DI\FactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Bermuda\Reflection\Reflection;
use function Bermuda\Stdlib\to_array;

/**
 * Parameter resolver for mapping PSR-7 request data to method parameters.
 *
 * This resolver automatically extracts and maps data from various parts of an HTTP request
 * (query parameters, request body, query string) to method parameters based on custom
 * mapping attributes. It supports both direct data mapping and object instantiation
 * through dependency injection.
 *
 * Supported mapping attributes:
 * - MapQueryParameter: Maps individual query parameters
 * - MapQueryString: Maps entire query string with field remapping
 * - MapRequestPayload: Maps request body data with field remapping
 */
final class RequestMapperResolver implements ParameterResolverInterface
{
    /**
     * Creates a new request mapper resolver.
     *
     * @param FactoryInterface $factory Factory for creating objects with dependency injection
     */
    public function __construct(
        private readonly FactoryInterface $factory
    ) {}

    /**
     * Resolves parameter values from PSR-7 request data based on mapping attributes.
     *
     * Resolution workflow:
     * 1. Check for mapping attributes on the parameter
     * 2. Extract PSR-7 request from provided parameters
     * 3. Extract relevant data based on attribute type
     * 4. For array parameters: return extracted data directly
     * 5. For class parameters: instantiate object via factory with merged data
     *
     * @param \ReflectionParameter $parameter The parameter to resolve
     * @param array $providedParameters Parameters including the PSR-7 request instance
     * @param array $resolvedParameters Previously resolved parameters (unused)
     * @return array{0: int, 1: mixed}|null Array with parameter position and resolved value, or null
     * @throws ParameterResolutionException When request is missing or object creation fails
     */
    public function resolve(\ReflectionParameter $parameter, array $providedParameters = [], array $resolvedParameters = []): ?array
    {
        $attribute = $this->getAttribute($parameter);
        if ($attribute === null) {
            return null;
        }

        $request = $this->extractRequest($parameter, $providedParameters, $resolvedParameters);
        $data = $this->extractData($attribute, $request, $parameter->getName());

        // Handle array type parameters - return data directly
        if ($parameter->getType()?->getName() === 'array') {
            return [$parameter->getPosition(), $data];
        }

        // Handle class type parameters - instantiate via factory
        $className = $parameter->getType()?->getName();
        if ($className && class_exists($className)) {
            try {
                $instance = $this->factory->make(
                    $className,
                    array_merge($request->getAttributes(), $data)
                );
                return [$parameter->getPosition(), $instance];
            } catch (\Throwable $previous) {
                throw ParameterResolutionException::createFromPrev(
                    $parameter,
                    $providedParameters,
                    $resolvedParameters,
                    $previous
                );
            }
        }

        return null;
    }

    /**
     * Extracts request data based on the mapping attribute type.
     *
     * @param object $attribute The mapping attribute instance
     * @param ServerRequestInterface $request The PSR-7 request
     * @param string $paramName The parameter name for fallback mapping
     * @return array Extracted and mapped data
     * @throws \OutOfBoundsException When required query parameter is missing
     */
    private function extractData(object $attribute, ServerRequestInterface $request, string $paramName): array
    {
        return match (true) {
            $attribute instanceof MapQueryParameter => $this->extractQueryParameter($request, $attribute, $paramName),
            $attribute instanceof MapRequestPayload => $this->map(to_array($request->getParsedBody()), $attribute->map),
            $attribute instanceof MapQueryString => $this->map($request->getQueryParams(), $attribute->map),
            default => []
        };
    }

    /**
     * Extracts a specific query parameter from the request.
     *
     * @param ServerRequestInterface $request The PSR-7 request
     * @param MapQueryParameter $attribute The query parameter mapping attribute
     * @param string $paramName Default parameter name if attribute name is not specified
     * @return array Associative array with parameter name and value
     * @throws \OutOfBoundsException When the required query parameter is missing
     */
    private function extractQueryParameter(ServerRequestInterface $request, MapQueryParameter $attribute, string $paramName): array
    {
        $queryParams = $request->getQueryParams();
        $queryParamName = $attribute->name ?? $paramName;

        if (!array_key_exists($queryParamName, $queryParams)) {
            throw new \OutOfBoundsException(
                sprintf(
                    "Required query parameter '%s' is missing from the request. Available parameters: [%s]",
                    $queryParamName,
                    implode(', ', array_keys($queryParams))
                )
            );
        }

        return [$paramName => $queryParams[$queryParamName]];
    }

    /**
     * Applies field name mapping to the provided data array.
     *
     * Transforms field names according to the mapping rules, removing original
     * field names and adding new ones with the same values.
     *
     * @param array $data The original data array
     * @param array $map Mapping rules (source_field => target_field)
     * @return array Data array with renamed fields
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
     * Extracts the PSR-7 request from provided parameters.
     *
     * @param \ReflectionParameter $parameter The parameter being resolved (for error context)
     * @param array $providedParameters Parameters array that should contain the request
     * @param array $resolvedParameters Previously resolved parameters (for error context)
     * @return ServerRequestInterface The extracted request instance
     * @throws ParameterResolutionException When request is not found or invalid
     */
    private function extractRequest(\ReflectionParameter $parameter, array $providedParameters, array $resolvedParameters): ServerRequestInterface
    {
        if (!RequestParameter::has($providedParameters)) {
            throw new ParameterResolutionException(
                $parameter,
                $providedParameters,
                $resolvedParameters,
                sprintf(
                    "PSR-7 request instance not found in provided parameters. Expected \$providedParameters['%s'] to be an instance of %s",
                    RequestParameter::KEY,
                    ServerRequestInterface::class
                )
            );
        }

        return RequestParameter::get($providedParameters);
    }

    /**
     * Retrieves the first applicable mapping attribute from the parameter.
     *
     * Searches for supported mapping attributes in order of precedence:
     * 1. MapQueryParameter (specific query parameter)
     * 2. MapQueryString (entire query string)
     * 3. MapRequestPayload (request body)
     *
     * @param \ReflectionParameter $parameter The parameter to inspect
     * @return object|null The first mapping attribute found, or null if none exist
     */
    private function getAttribute(\ReflectionParameter $parameter): ?object
    {
        $attributeClasses = [
            MapQueryParameter::class,
            MapQueryString::class,
            MapRequestPayload::class
        ];

        foreach ($attributeClasses as $attributeClass) {
            $attribute = Reflection::getFirstMetadata($parameter, $attributeClass);
            if ($attribute) {
                return $attribute;
            }
        }

        return null;
    }
}