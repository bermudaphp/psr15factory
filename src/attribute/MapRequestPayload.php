<?php

namespace Bermuda\MiddlewareFactory\Attribute;

/**
 * Attribute for mapping request payload data to method parameters.
 *
 * This attribute enables automatic extraction and mapping of data from the request's
 * parsed body (typically JSON, form data, or XML) to method parameters. It supports
 * field name remapping to transform external API field names to internal parameter names.
 *
 * Usage examples:
 * - #[MapRequestPayload] - Maps entire payload to parameter
 * - #[MapRequestPayload(['external_field' => 'internal_field'])] - Maps with field renaming
 *
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class MapRequestPayload
{
    /**
     * Creates a new request payload mapping attribute.
     *
     * @param array $map Associative array for field name mapping.
     *                   Keys are source field names from request payload,
     *                   values are target parameter names.
     *                   Empty array means no field remapping.
     */
    public function __construct(
        public readonly array $map = []
    ) {
    }
}