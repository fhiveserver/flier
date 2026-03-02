<?php

declare(strict_types=1);

namespace FHIR\Flier\Patch;

use FHIR\Flier\Builder\Operations\AddOperation;
use FHIR\Flier\Builder\Operations\DeleteOperation;
use FHIR\Flier\Builder\Operations\Operation;
use FHIR\Flier\Builder\Operations\ReplaceOperation;

/**
 * Gera um recurso FHIR Parameters com operações FHIRPath Patch.
 * Generates a FHIR Parameters resource with FHIRPath Patch operations.
 *
 * Especificação / Spec: https://hl7.org/fhir/R4/fhirpatch.html
 *
 * Uso / Usage:
 *   $params = Flier::resource('Patient', $data)
 *       ->birthDate()->delete()
 *       ->name([['family' => 'Doe']])
 *       ->status()->replace('active')
 *       ->asFHIRPatch();
 *
 *   // Resultado / Result:
 *   // {
 *   //   "resourceType": "Parameters",
 *   //   "parameter": [
 *   //     {"name": "operation", "part": [
 *   //       {"name": "type",  "valueCode":   "delete"},
 *   //       {"name": "path",  "valueString": "Patient.birthDate"}
 *   //     ]},
 *   //     {"name": "operation", "part": [
 *   //       {"name": "type",  "valueCode":   "add"},
 *   //       {"name": "path",  "valueString": "Patient"},
 *   //       {"name": "name",  "valueString": "name"},
 *   //       {"name": "value", "valueString": "[{\"family\":\"Doe\"}]"}
 *   //     ]},
 *   //     ...
 *   //   ]
 *   // }
 */
final class FHIRPathPatchDriver
{
    /**
     * Gera o recurso Parameters com todas as operações FHIRPath Patch.
     * Generates the Parameters resource with all FHIRPath Patch operations.
     *
     * @param  list<Operation>  $operations
     * @return array<string, mixed>
     */
    public function generate(string $resourceType, array $operations): array
    {
        $parameters = array_map(
            fn (Operation $op) => $this->buildParameter($resourceType, $op),
            $operations,
        );

        return [
            'resourceType' => 'Parameters',
            'parameter' => $parameters,
        ];
    }

    /**
     * Monta o parâmetro "operation" para uma única operação.
     * Builds the "operation" parameter for a single operation.
     *
     * @return array<string, mixed>
     */
    private function buildParameter(string $resourceType, Operation $operation): array
    {
        return [
            'name' => 'operation',
            'part' => $this->buildParts($resourceType, $operation),
        ];
    }

    /**
     * Monta as parts da operação conforme o tipo.
     * Builds the operation parts according to the type.
     *
     * @return list<array<string, mixed>>
     */
    private function buildParts(string $resourceType, Operation $operation): array
    {
        return match (true) {
            $operation instanceof AddOperation => $this->addParts($resourceType, $operation),
            $operation instanceof ReplaceOperation => $this->replaceParts($resourceType, $operation),
            $operation instanceof DeleteOperation => $this->deleteParts($resourceType, $operation),
            default => [],
        };
    }

    /**
     * Parts para type="add": path aponta para o recurso pai, name é a propriedade.
     * Parts for type="add": path points to parent resource, name is the property.
     *
     * @return list<array<string, mixed>>
     */
    private function addParts(string $resourceType, AddOperation $operation): array
    {
        return [
            ['name' => 'type', 'valueCode' => 'add'],
            ['name' => 'path', 'valueString' => $resourceType],
            ['name' => 'name', 'valueString' => $operation->getProperty()],
            ...$this->valueParts($operation->getValue()),
        ];
    }

    /**
     * Parts para type="replace": path aponta diretamente para a propriedade.
     * Parts for type="replace": path points directly to the property.
     *
     * @return list<array<string, mixed>>
     */
    private function replaceParts(string $resourceType, ReplaceOperation $operation): array
    {
        return [
            ['name' => 'type', 'valueCode' => 'replace'],
            ['name' => 'path', 'valueString' => "{$resourceType}.{$operation->getProperty()}"],
            ...$this->valueParts($operation->getValue()),
        ];
    }

    /**
     * Parts para type="delete": apenas path, sem value.
     * Parts for type="delete": only path, no value.
     *
     * @return list<array<string, mixed>>
     */
    private function deleteParts(string $resourceType, DeleteOperation $operation): array
    {
        return [
            ['name' => 'type', 'valueCode' => 'delete'],
            ['name' => 'path', 'valueString' => "{$resourceType}.{$operation->getProperty()}"],
        ];
    }

    /**
     * Detecta o tipo primitivo FHIR do valor e retorna a part value[x].
     * Detects the FHIR primitive type of the value and returns the value[x] part.
     *
     * Tipos complexos (array/object) são serializados como valueString JSON.
     * Complex types (array/object) are serialized as JSON valueString.
     *
     * @return list<array<string, mixed>>
     */
    private function valueParts(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        $fhirKey = $this->detectValueKey($value);

        $serialized = is_array($value) ? json_encode($value) : $value;

        return [['name' => 'value', $fhirKey => $serialized]];
    }

    /**
     * Mapeia o tipo PHP → chave value[x] do FHIR.
     * Maps PHP type → FHIR value[x] key.
     *
     * Datas e dateTimes são detectados por pattern, não por tipo PHP.
     * Dates and dateTimes are detected by pattern, not PHP type.
     */
    private function detectValueKey(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'valueBoolean',
            is_int($value) => 'valueInteger',
            is_float($value) => 'valueDecimal',
            is_array($value) => 'valueString',
            is_string($value) && $this->looksLikeDate($value) => 'valueDate',
            is_string($value) && $this->looksLikeDateTime($value) => 'valueDateTime',
            default => 'valueString',
        };
    }

    /**
     * Verifica se a string parece uma date FHIR (YYYY-MM-DD).
     * Checks if the string looks like a FHIR date (YYYY-MM-DD).
     */
    private function looksLikeDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }

    /**
     * Verifica se a string parece um dateTime FHIR (YYYY-MM-DDTHH:MM...).
     * Checks if the string looks like a FHIR dateTime (YYYY-MM-DDTHH:MM...).
     */
    private function looksLikeDateTime(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $value);
    }
}
