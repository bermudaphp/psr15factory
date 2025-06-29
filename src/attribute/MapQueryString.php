<?php

namespace Bermuda\MiddlewareFactory\Attribute;

/**
 * PHP 8+ attribute for mapping entire query strings to method parameters with field remapping.
 *
 * This attribute enables automatic extraction and transformation of all query string
 * parameters from PSR-7 requests, with optional field name remapping to match internal
 * naming conventions. Unlike MapQueryParameter which extracts individual parameters,
 * this attribute processes the entire query string as a collection.
 *
 * The mapping functionality allows transformation of external query parameter names
 * to internal field names, enabling clean separation between public API contracts
 * and internal implementation details. This is particularly valuable when working
 * with legacy APIs or when maintaining backward compatibility.
 *
 * Key features:
 * - Entire query string extraction
 * - Flexible field name remapping via associative array
 * - Support for nested parameter structures
 * - Integration with data transfer objects and arrays
 * - Preservation of unmapped parameters
 *
 * Common use cases:
 * - Search and filtering interfaces with multiple parameters
 * - Configuration and settings management
 * - API versioning and parameter evolution
 * - Data transfer object population
 * - Complex query parameter validation
 *
 * @example Basic query string extraction
 * ```php
 * class SearchController
 * {
 *     public function search(#[MapQueryString] array $filters): ResponseInterface
 *     {
 *         // ?name=John&age=25&city=NYC
 *         // $filters = ['name' => 'John', 'age' => '25', 'city' => 'NYC']
 *     }
 * }
 * ```
 *
 * @example Field name remapping
 * ```php
 * class ProductController
 * {
 *     public function list(
 *         #[MapQueryString(['sort' => 'sortBy', 'dir' => 'direction', 'q' => 'search'])]
 *         array $queryParams
 *     ): ResponseInterface {
 *         // ?sort=price&dir=asc&q=laptop&limit=10
 *         // $queryParams = [
 *         //     'sortBy' => 'price',
 *         //     'direction' => 'asc',
 *         //     'search' => 'laptop',
 *         //     'limit' => '10'  // unmapped parameters preserved
 *         // ]
 *     }
 * }
 * ```
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class MapQueryString
{
    /**
     * Creates a new query string mapping attribute with optional field remapping.
     *
     * The mapping array allows transformation of external query parameter names
     * to internal field names. Parameters not included in the mapping are passed
     * through unchanged, providing a flexible approach to parameter transformation.
     *
     * Mapping rules:
     * - Keys represent the original query parameter names
     * - Values represent the target field names in the result array
     * - Unmapped parameters are preserved with their original names
     * - Empty mapping array means no transformation (pass-through)
     *
     * @param array $map Associative array for field name mapping.
     *                   Format: ['external_param' => 'internal_field', ...]
     *                   Empty array (default) means no field remapping.
     *
     * @example No remapping (pass-through)
     * ```php
     * public function filter(#[MapQueryString] array $params): ResponseInterface
     * {
     *     // ?category=books&price_min=10&price_max=50
     *     // $params = ['category' => 'books', 'price_min' => '10', 'price_max' => '50']
     * }
     * ```
     *
     * @example Selective remapping
     * ```php
     * public function search(
     *     #[MapQueryString(['q' => 'searchTerm', 'cat' => 'category'])]
     *     array $searchParams
     * ): ResponseInterface {
     *     // ?q=phone&cat=electronics&brand=apple&limit=20
     *     // $searchParams = [
     *     //     'searchTerm' => 'phone',  // q -> searchTerm
     *     //     'category' => 'electronics',  // cat -> category
     *     //     'brand' => 'apple',       // preserved
     *     //     'limit' => '20'           // preserved
     *     // ]
     * }
     * ```
     *
     * @example Complex mapping for legacy API compatibility
     * ```php
     * public function legacySearch(
     *     #[MapQueryString([
     *         'nm' => 'name',
     *         'loc' => 'location',
     *         'dt_from' => 'dateFrom',
     *         'dt_to' => 'dateTo'
     *     ])]
     *     SearchCriteria $criteria
     * ): ResponseInterface {
     *     // Transforms legacy parameter names to modern DTO fields
     * }
     * ```
     */
    public function __construct(
        public readonly array $map = []
    ) {
    }
}