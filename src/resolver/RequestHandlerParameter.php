<?php

namespace Bermuda\MiddlewareFactory\Resolver;

use Bermuda\ParameterResolver\ResolverException;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class RequestHandlerParameter
 *
 * This utility class provides helper methods to manage the inclusion of a RequestHandlerInterface
 * instance within an associative array of parameters. It uses the fully-qualified class name
 * of RequestHandlerInterface as a unique key to store and retrieve the handler.
 *
 * Methods include:
 * - set(array $params, RequestHandlerInterface $handler): Inserts the given request handler into the parameters array.
 * - has(array $params): Checks whether the parameters array contains a valid RequestHandlerInterface instance.
 * - get(array $params): Retrieves the request handler from the parameters array, throwing a ResolverException
 *   if the handler is missing or invalid.
 *
 * **Note:** The exception messages refer to a constant (self::PARAMETER_KEY), which should correspond to the
 * defined key (i.e., self::KEY). Make sure this constant name is consistent to avoid confusion.
 */
final class RequestHandlerParameter
{
    /**
     * Unique key used to store the RequestHandlerInterface instance in the parameters array.
     */
    public const string KEY = RequestHandlerInterface::class;

    /**
     * Inserts a RequestHandlerInterface instance into the parameters array.
     *
     * @param array $providedParameters An associative array of parameters.
     * @param RequestHandlerInterface $handler The RequestHandlerInterface instance to insert.
     *
     * @return array The updated parameters array with the RequestHandlerInterface instance added.
     */
    public static function set(array $providedParameters, RequestHandlerInterface $handler): array
    {
        $providedParameters[self::KEY] = $handler;
        return $providedParameters;
    }

    /**
     * Checks if the parameters array contains a valid RequestHandlerInterface instance.
     *
     * @param array $providedParameters An associative array of parameters.
     *
     * @return bool True if a valid RequestHandlerInterface instance is found under the designated key, false otherwise.
     */
    public static function has(array $providedParameters): bool
    {
        return isset($providedParameters[self::KEY]) && $providedParameters[self::KEY] instanceof RequestHandlerInterface;
    }

    /**
     * Retrieves the RequestHandlerInterface instance from the parameters array.
     *
     * @param array $providedParameters An associative array of parameters.
     *
     * @return ?RequestHandlerInterface The server request instance stored in the parameters array.
     *
     */
    public static function get(array $providedParameters): ?RequestHandlerInterface
    {
        return $providedParameters[self::KEY];
    }
}