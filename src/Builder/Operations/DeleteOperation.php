<?php

declare(strict_types=1);

namespace FHIR\Flier\Builder\Operations;

/**
 * Remove uma propriedade do recurso FHIR.
 * Removes a property from the FHIR resource.
 *
 * Corresponde ao type="delete" do FHIRPath Patch.
 * Corresponds to type="delete" in FHIRPath Patch.
 */
final readonly class DeleteOperation implements Operation
{
    public function __construct(
        private string $property,
    ) {}

    public function getProperty(): string
    {
        return $this->property;
    }

    public function getType(): string
    {
        return 'delete';
    }
}
