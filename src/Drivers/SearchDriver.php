<?php

declare(strict_types=1);

namespace FHIR\Flier\Drivers;

use FHIR\Flier\Builder\SearchParam;

/**
 * Contrato para drivers de busca FHIR.
 * Contract for FHIR search drivers.
 *
 * Um driver recebe os parâmetros acumulados pelo SearchBuilder e executa a busca:
 * via URL para servidor externo, via search_indexes local, via HTTP, etc.
 *
 * A driver receives the parameters accumulated by SearchBuilder and executes the search:
 * via URL for external server, via local search_indexes, via HTTP, etc.
 */
interface SearchDriver
{
    /**
     * Executa a busca com os parâmetros acumulados.
     * Executes the search with the accumulated parameters.
     *
     * @param  list<SearchParam>  $params
     */
    public function search(string $resourceType, array $params): mixed;
}
