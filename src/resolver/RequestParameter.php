<?php

namespace Bermuda\MiddlewareFactory\Resolver;

use Bermuda\ParameterResolver\ResolverException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class RequestParameter
 *
 * This utility class provides helper methods to manage the inclusion of a ServerRequestInterface
 * instance within an associative array of parameters. It uses the fully-qualified class name
 * of ServerRequestInterface as a unique key to store and retrieve the request.
 *
 * Methods include:
 * - set(array $providedParameters, ServerRequestInterface $request): Inserts the given server request into the parameters array.
 * - has(array $providedParameters): Checks whether the parameters array contains a valid ServerRequestInterface instance.
 * - get(array $providedParameters): Retrieves the server request from the parameters array, throwing a ResolverException
 *   if the request is missing or invalid.
 *
 * **Note:** The exception messages refer to a constant (self::PARAMETER_KEY), which should correspond to the
 * defined key (i.e., self::KEY). Make sure this constant name is consistent to avoid confusion.
 */
final class RequestParameter
{
    /**
     * Unique key used to store the ServerRequestInterface instance in the parameters array.
     */
    public const string KEY = ServerRequestInterface::class;

    /**
     * Inserts a ServerRequestInterface instance into the parameters array.
     *
     * @param array $providedParameters An associative array of parameters.
     * @param ServerRequestInterface $request The server request instance to insert.
     *
     * @return array The updated parameters array with the server request added.
     */
    public static function set(array $providedParameters, ServerRequestInterface $request): array
    {
        $providedParameters[self::KEY] = $request;
        return $providedParameters;
    }

    /**
     * Checks if the parameters array contains a valid ServerRequestInterface instance.
     *
     * @param array $providedParameters An associative array of parameters.
     *
     * @return bool True if a valid ServerRequestInterface instance is found under the designated key, false otherwise.
     */
    public static function has(array $providedParameters): bool
    {
        return isset($providedParameters[self::KEY]) && $providedParameters[self::KEY] instanceof ServerRequestInterface;
    }

    /**
     * Retrieves the ServerRequestInterface instance from the parameters array.
     *
     * @param array $providedParameters An associative array of parameters.
     *
     * @return ?ServerRequestInterface The server request instance stored in the parameters array.
     *
     */
    public static function get(array $providedParameters): ?ServerRequestInterface
    {
        return $providedParameters[self::KEY];
    }
}
