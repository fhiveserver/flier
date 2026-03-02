<?php

declare(strict_types=1);

namespace FHIR\Flier\Builder\Operations;

/**
 * Adiciona (ou define) uma propriedade no recurso FHIR.
 * Adds (or sets) a property on the FHIR resource.
 *
 * Corresponde ao type="add" do FHIRPath Patch.
 * Corresponds to type="add" in FHIRPath Patch.
 */
final readonly class AddOperation implements Operation
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
        return 'add';
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}
