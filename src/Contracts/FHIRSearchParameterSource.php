<?php

declare(strict_types=1);

namespace FHIR\Flier\Contracts;

/**
 * Fonte de SearchParameters FHIR para o motor de indexação.
 * Source of FHIR SearchParameters for the indexing engine.
 *
 * Implementações concretas vivem em modules/search/ — acopladas ao banco do Fhive.
 * Concrete implementations live in modules/search/ — coupled to Fhive's DB.
 */
interface FHIRSearchParameterSource
{
    /**
     * Retorna todos os SearchParameters aplicáveis a um tipo de recurso.
     * Returns all SearchParameters applicable to a resource type.
     *
     * @return list<FHIRSearchParameter>
     */
    public function forType(string $resourceType): array;
}
