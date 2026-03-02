<?php

declare(strict_types=1);

namespace FHIR\Flier\Drivers;

use FHIR\Flier\Builder\Operations\Operation;

/**
 * Contrato para drivers de operações em recursos FHIR.
 * Contract for FHIR resource operation drivers.
 *
 * Um driver recebe as operações acumuladas pelo ResourceBuilder e as materializa:
 * aplica no banco, envia HTTP, gera patch, etc.
 *
 * A driver receives the operations accumulated by ResourceBuilder and materializes them:
 * applies to DB, sends HTTP, generates patch, etc.
 */
interface ResourceDriver
{
    /**
     * Cria o recurso com os dados + operações aplicadas.
     * Creates the resource with data + applied operations.
     *
     * @param  array<string, mixed>  $data
     * @param  list<Operation>  $operations
     */
    public function create(string $resourceType, array $data, array $operations): mixed;

    /**
     * Atualiza (patch/merge) o recurso com as operações acumuladas.
     * Updates (patch/merge) the resource with the accumulated operations.
     *
     * @param  array<string, mixed>  $data
     * @param  list<Operation>  $operations
     */
    public function update(string $resourceType, array $data, array $operations): mixed;

    /**
     * Substitui o recurso inteiro (PUT semântico).
     * Replaces the entire resource (PUT semantics).
     *
     * @param  array<string, mixed>  $data
     * @param  list<Operation>  $operations
     */
    public function put(string $resourceType, array $data, array $operations): mixed;

    /**
     * Remove o recurso.
     * Deletes the resource.
     *
     * @param  array<string, mixed>  $data
     */
    public function delete(string $resourceType, array $data): mixed;
}
