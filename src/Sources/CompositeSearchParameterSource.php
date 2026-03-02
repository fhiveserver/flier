<?php

declare(strict_types=1);

namespace FHIR\Flier\Sources;

use FHIR\Flier\Contracts\FHIRSearchParameter;
use FHIR\Flier\Contracts\FHIRSearchParameterSource;

/**
 * Agrega múltiplas fontes de SearchParameter em uma única.
 * Aggregates multiple SearchParameter sources into one.
 *
 * Ordem importa: a primeira fonte que define um código vence (sem duplicatas por código).
 * Order matters: the first source that defines a code wins (no duplicates by code).
 *
 * @param  list<FHIRSearchParameterSource>  $sources
 */
final class CompositeSearchParameterSource implements FHIRSearchParameterSource
{
    /** @param list<FHIRSearchParameterSource> $sources */
    public function __construct(
        private array $sources = [],
    ) {}

    /**
     * Adiciona uma fonte ao composite (chamado em ServiceProvider::boot()).
     * Adds a source to the composite (called in ServiceProvider::boot()).
     */
    public function add(FHIRSearchParameterSource $source): void
    {
        $this->sources[] = $source;
    }

    /** @return list<FHIRSearchParameter> */
    public function forType(string $resourceType): array
    {
        $seen = [];
        $merged = [];

        foreach ($this->sources as $source) {
            foreach ($source->forType($resourceType) as $param) {
                if (isset($seen[$param->code])) {
                    continue;
                }

                $seen[$param->code] = true;
                $merged[] = $param;
            }
        }

        return $merged;
    }
}
