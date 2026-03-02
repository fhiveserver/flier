<?php

declare(strict_types=1);

namespace FHIR\Flier\Builder;

/**
 * Parâmetro de busca FHIR parsed a partir da query string HTTP ou do builder fluente.
 * Parsed FHIR search parameter from HTTP query string or fluent builder.
 */
final readonly class SearchParam
{
    public function __construct(
        public string $code,
        public string $type,          // string|token|date|reference|number|quantity|uri
        public string $rawValue,
        public ?string $modifier = null,
        public ?string $prefix = null,     // gt|lt|ge|le|eq|ne|sa|eb|ap
        public ?string $tokenSystem = null,
        public ?string $tokenCode = null,
    ) {}
}
