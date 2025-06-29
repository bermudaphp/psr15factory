<?php

namespace Bermuda\MiddlewareFactory\Resolver;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Utility class for managing PSR-7 ServerRequestInterface instances in parameter arrays.
 *
 * This class provides a standardized way to store, retrieve, and check for PSR-7 server
 * request instances within associative parameter arrays used by the middleware factory
 * and parameter resolution system.
 *
 * The class uses the fully-qualified interface name as a unique key to avoid conflicts
 * with other parameter types and ensures type safety through strict instance checking.
 *
 * Usage patterns:
 * - Parameter resolvers use this to extract request data for mapping
 * - Middleware adapters use this to provide request context to callables
 * - Factory strategies use this to pass request instances between components
 *
 * Thread safety: This class contains only static methods and no mutable state,
 * making it safe for concurrent use in multi-threaded environments.
 */
final class RequestParameter
{
    /**
     * Unique key for storing ServerRequestInterface instances in parameter arrays.
     *
     * Using the fully-qualified interface name ensures uniqueness and provides
     * self-documenting parameter keys that clearly indicate the expected type.
     */
    public const string KEY = ServerRequestInterface::class;

    /**
     * Stores a PSR-7 server request instance in the provided parameters array.
     *
     * This method creates a new parameter array with the server request instance
     * added under the standardized key. The original array is not modified,
     * ensuring immutability and preventing unintended side effects.
     *
     * @param array $providedParameters The existing parameter array to extend
     * @param ServerRequestInterface $request The server request instance to store
     * @return array A new parameter array containing the request instance
     *
     * @example
     * ```php
     * $params = [];
     * $params = RequestParameter::set($params, $serverRequest);
     * // $params now contains the request under the standardized key
     * ```
     */
    public static function set(array $providedParameters, ServerRequestInterface $request): array
    {
        $providedParameters[self::KEY] = $request;
        return $providedParameters;
    }

    /**
     * Checks whether the parameter array contains a valid PSR-7 server request instance.
     *
     * This method performs both existence and type validation to ensure that:
     * 1. The standardized key exists in the parameter array
     * 2. The value is actually an instance of ServerRequestInterface
     *
     * This dual validation prevents false positives when the key exists but
     * contains an invalid value (e.g., null, string, or wrong object type).
     *
     * @param array $providedParameters The parameter array to check
     * @return bool True if a valid ServerRequestInterface instance is present, false otherwise
     *
     * @example
     * ```php
     * if (RequestParameter::has($params)) {
     *     $request = RequestParameter::get($params);
     *     // Safe to use $request as ServerRequestInterface
     * }
     * ```
     */
    public static function has(array $providedParameters): bool
    {
        return isset($providedParameters[self::KEY])
            && $providedParameters[self::KEY] instanceof ServerRequestInterface;
    }

    /**
     * Retrieves the PSR-7 server request instance from the parameter array.
     *
     * This method extracts the server request instance stored under the standardized
     * key. It returns null if the request is not present or is not a valid instance,
     * allowing for safe optional extraction without exceptions.
     *
     * For guaranteed extraction (when you know the request should be present),
     * use `has()` first to validate presence, or handle the null return appropriately.
     *
     * @param array $providedParameters The parameter array containing the request
     * @return ServerRequestInterface|null The server request instance, or null if not found/invalid
     *
     * @example
     * ```php
     * $request = RequestParameter::get($params);
     * if ($request !== null) {
     *     // Process the request
     *     $method = $request->getMethod();
     * }
     * ```
     */
    public static function get(array $providedParameters): ?ServerRequestInterface
    {
        return $providedParameters[self::KEY] ?? null;
    }
}