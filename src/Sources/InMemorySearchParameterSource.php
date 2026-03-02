<?php

declare(strict_types=1);

namespace FHIR\Flier\Sources;

use FHIR\Flier\Contracts\FHIRSearchParameter;
use FHIR\Flier\Contracts\FHIRSearchParameterSource;

/**
 * Fonte de SearchParameters definida em código — para uso em testes e em recursos hard-coded.
 * Code-defined SearchParameter source — for use in tests and hard-coded resources.
 *
 * @param  array<string, list<FHIRSearchParameter>>  $params
 */
final class InMemorySearchParameterSource implements FHIRSearchParameterSource
{
    /** @param array<string, list<FHIRSearchParameter>> $params */
    public function __construct(
        private readonly array $params = [],
    ) {}

    /**
     * Named constructor para um único tipo de recurso.
     * Named constructor for a single resource type.
     *
     * @param  list<FHIRSearchParameter>  $params
     */
    public static function only(string $resourceType, array $params): self
    {
        return new self([$resourceType => $params]);
    }

    /** @return list<FHIRSearchParameter> */
    public function forType(string $resourceType): array
    {
        return $this->params[$resourceType] ?? [];
    }
}
