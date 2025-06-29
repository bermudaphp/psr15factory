<?php

namespace Bermuda\MiddlewareFactory\Attribute;

/**
 * PHP 8+ attribute for mapping individual query parameters to method parameters.
 *
 * This attribute enables automatic extraction of specific query string parameters
 * from PSR-7 requests and injection into method parameters during middleware
 * processing. It's designed for scenarios where you need individual query
 * parameters rather than the entire query string.
 *
 * The attribute provides flexible name mapping, allowing external query parameter
 * names to be mapped to different internal parameter names. This is particularly
 * useful when working with APIs that use different naming conventions (snake_case
 * vs camelCase, abbreviated names, etc.).
 *
 * Key features:
 * - Individual query parameter extraction
 * - Flexible name mapping (explicit or implicit)
 * - Type-safe parameter injection
 * - Validation and error handling for missing parameters
 * - Integration with PSR-7 query parameter lifecycle
 *
 * Common use cases:
 * - API endpoint parameters (page, limit, search terms)
 * - Filtering and sorting options
 * - Feature flags and configuration overrides
 * - User preferences and display options
 * - Resource identification and selection
 *
 * @example Basic parameter extraction
 * ```php
 * class SearchController
 * {
 *     public function search(
 *         #[MapQueryParameter] string $query,
 *         #[MapQueryParameter] int $page = 1
 *     ): ResponseInterface {
 *         // Extracts ?query=something&page=2
 *         // $query = "something", $page = 2
 *     }
 * }
 * ```
 *
 * @example Custom parameter name mapping
 * ```php
 * class ProductController
 * {
 *     public function list(
 *         #[MapQueryParameter('q')] string $searchQuery,
 *         #[MapQueryParameter('per_page')] int $itemsPerPage = 10,
 *         #[MapQueryParameter('sort_by')] string $sortField = 'name'
 *     ): ResponseInterface {
 *         // Maps ?q=phone&per_page=20&sort_by=price
 *         // $searchQuery = "phone", $itemsPerPage = 20, $sortField = "price"
 *     }
 * }
 * ```
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class MapQueryParameter
{
    /**
     * Creates a new query parameter mapping attribute.
     *
     * When no name is specified, the parameter resolver will use the method
     * parameter's name as the query parameter key. This follows the principle
     * of convention over configuration, reducing boilerplate code while still
     * allowing explicit customization when needed.
     *
     * @param string|null $name The name of the query parameter to extract.
     *                          If null, uses the method parameter name.
     *                          This enables flexible mapping between external
     *                          query parameter names and internal parameter names.
     *
     * @example Implicit name mapping
     * ```php
     * public function filter(#[MapQueryParameter] string $category): ResponseInterface
     * {
     *     // Extracts from ?category=electronics
     *     // $category = "electronics"
     * }
     * ```
     *
     * @example Explicit name mapping
     * ```php
     * public function paginate(#[MapQueryParameter('p')] int $pageNumber): ResponseInterface
     * {
     *     // Extracts from ?p=5
     *     // $pageNumber = 5
     * }
     * ```
     *
     * @example Required vs optional parameters
     * ```php
     * public function search(
     *     #[MapQueryParameter] string $query,              // Required
     *     #[MapQueryParameter] ?string $category = null    // Optional
     * ): ResponseInterface {
     *     // ?query=laptop&category=electronics OR ?query=laptop
     * }
     * ```
     */
    public function __construct(
        public readonly ?string $name = null,
    ) {
    }
}