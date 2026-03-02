<?php

declare(strict_types=1);

namespace FHIR\Flier\Drivers;

use FHIR\Flier\Builder\Operations\AddOperation;
use FHIR\Flier\Builder\Operations\DeleteOperation;
use FHIR\Flier\Builder\Operations\Operation;
use FHIR\Flier\Builder\Operations\ReplaceOperation;

/**
 * Driver em memória que aplica operações sobre arrays PHP — sem persistência.
 * In-memory driver that applies operations on PHP arrays — no persistence.
 *
 * Útil para testes, transformações pipeline e pré-visualização de mudanças.
 * Useful for tests, pipeline transformations, and previewing changes.
 */
final class ArrayResourceDriver implements ResourceDriver
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<Operation>  $operations
     * @return array<string, mixed>
     */
    public function create(string $resourceType, array $data, array $operations): array
    {
        return $this->apply($data, $operations);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<Operation>  $operations
     * @return array<string, mixed>
     */
    public function update(string $resourceType, array $data, array $operations): array
    {
        return $this->apply($data, $operations);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<Operation>  $operations
     * @return array<string, mixed>
     */
    public function put(string $resourceType, array $data, array $operations): array
    {
        return $this->apply($data, $operations);
    }

    /**
     * Remove o recurso — retorna array vazio (sem persistência).
     * Deletes the resource — returns empty array (no persistence).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function delete(string $resourceType, array $data): array
    {
        return [];
    }

    /**
     * Aplica uma lista de operações sobre um array de dados FHIR.
     * Applies a list of operations on a FHIR data array.
     *
     * - AddOperation: define (ou sobrescreve) a propriedade / sets (or overwrites) the property
     * - ReplaceOperation: substitui apenas se a propriedade existir / replaces only if the property exists
     * - DeleteOperation: remove a propriedade / removes the property
     *
     * @param  array<string, mixed>  $data
     * @param  list<Operation>  $operations
     * @return array<string, mixed>
     */
    public function apply(array $data, array $operations): array
    {
        foreach ($operations as $operation) {
            $property = $operation->getProperty();

            if ($operation instanceof AddOperation) {
                $data[$property] = $operation->getValue();
            } elseif ($operation instanceof ReplaceOperation) {
                if (array_key_exists($property, $data)) {
                    $data[$property] = $operation->getValue();
                }
            } elseif ($operation instanceof DeleteOperation) {
                unset($data[$property]);
            }
        }

        return $data;
    }
}
