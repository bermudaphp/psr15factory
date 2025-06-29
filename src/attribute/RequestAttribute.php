<?php

namespace Bermuda\MiddlewareFactory\Attribute;

/**
 * PHP 8+ attribute for mapping PSR-7 request attributes to method parameters.
 *
 * This attribute enables automatic extraction of data stored in PSR-7 request attributes
 * and injection into method parameters during middleware resolution. Request attributes
 * are commonly used to pass data between middleware layers, such as user information,
 * route parameters, or computed values.
 *
 * The attribute supports both explicit attribute name specification and automatic
 * parameter name mapping, providing flexibility for different naming conventions
 * between external APIs and internal method signatures.
 *
 * Key features:
 * - Automatic parameter injection from request attributes
 * - Flexible name mapping (explicit or implicit)
 * - Type-safe parameter resolution
 * - Integration with PSR-7 request/attribute lifecycle
 *
 * Common use cases:
 * - Route parameter injection (user IDs, resource identifiers)
 * - Authentication context (user information, permissions)
 * - Computed middleware values (parsed data, validation results)
 * - Cross-cutting concerns (request tracing, feature flags)
 *
 * @example Basic usage with parameter name
 * ```php
 * class UserController
 * {
 *     public function getUser(#[RequestAttribute] int $userId): ResponseInterface
 *     {
 *         // $userId automatically extracted from $request->getAttribute('userId')
 *     }
 * }
 * ```
 *
 * @example Custom attribute name mapping
 * ```php
 * class ProfileController
 * {
 *     public function updateProfile(
 *         #[RequestAttribute('user_id')] int $currentUserId,
 *         #[RequestAttribute('profile_data')] array $profileInfo
 *     ): ResponseInterface {
 *         // Maps 'user_id' attribute to $currentUserId parameter
 *         // Maps 'profile_data' attribute to $profileInfo parameter
 *     }
 * }
 * ```
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class RequestAttribute
{
    /**
     * Creates a new request attribute mapping.
     *
     * When no name is specified, the parameter resolver will use the method
     * parameter's name as the attribute key. This convention-over-configuration
     * approach reduces boilerplate while allowing explicit override when needed.
     *
     * @param string|null $name The name of the request attribute to extract.
     *                          If null, uses the parameter name automatically.
     *                          This allows for flexible mapping between external
     *                          attribute names and internal parameter names.
     *
     * @example Implicit name mapping
     * ```php
     * public function handle(#[RequestAttribute] string $token): ResponseInterface
     * {
     *     // Extracts from $request->getAttribute('token')
     * }
     * ```
     *
     * @example Explicit name mapping
     * ```php
     * public function handle(#[RequestAttribute('auth_token')] string $token): ResponseInterface
     * {
     *     // Extracts from $request->getAttribute('auth_token')
     * }
     * ```
     */
    public function __construct(
        public readonly ?string $name = null,
    ) {
    }
}