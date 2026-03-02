<?php

declare(strict_types=1);

namespace FHIR\Flier\Builder\Operations;

/**
 * Substitui o valor de uma propriedade existente no recurso FHIR.
 * Replaces the value of an existing property on the FHIR resource.
 *
 * Diferente de AddOperation: falha se a propriedade não existir.
 * Unlike AddOperation: fails if the property does not exist.
 *
 * Corresponde ao type="replace" do FHIRPath Patch.
 * Corresponds to type="replace" in FHIRPath Patch.
 */
final readonly class ReplaceOperation implements Operation
{
    public function __construct(
        private string $property,
        private mixed $value,
    ) {}

    public function getProperty(): string
    {
        return $this->property;
    }

    public function getType(): string
    {
        return 'replace';
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}
