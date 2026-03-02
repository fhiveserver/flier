<?php

declare(strict_types=1);

namespace FHIR\Flier\Builder;

use FHIR\Flier\Builder\Operations\DeleteOperation;
use FHIR\Flier\Builder\Operations\ReplaceOperation;
use Stringable;

/**
 * Cursor imutável para uma propriedade FHIR — permite operações encadeadas.
 * Immutable cursor for a FHIR property — allows chained operations.
 *
 * Retornado por `$builder->property()` (sem argumentos).
 * Returned by `$builder->property()` (no arguments).
 *
 * Uso / Usage:
 *   $builder->birthDate()->delete()          → remove a propriedade
 *   $builder->status()->replace('active')    → substitui o valor
 *   $builder->name()->value()               → lê o valor atual
 */
final class PropertyProxy implements Stringable
{
    public function __construct(
        private readonly string $propertyName,
        private readonly mixed $currentValue,
        private readonly ResourceBuilder $parent,
    ) {}

    /**
     * Remove a propriedade do recurso.
     * Removes the property from the resource.
     *
     * Gera type="delete" no FHIRPath Patch.
     * Generates type="delete" in FHIRPath Patch.
     */
    public function delete(): ResourceBuilder
    {
        return $this->parent->addOperation(new DeleteOperation($this->propertyName));
    }

    /**
     * Substitui o valor atual da propriedade (a propriedade deve existir).
     * Replaces the current property value (property must already exist).
     *
     * Gera type="replace" no FHIRPath Patch.
     * Generates type="replace" in FHIRPath Patch.
     */
    public function replace(mixed $value): ResourceBuilder
    {
        return $this->parent->addOperation(new ReplaceOperation($this->propertyName, $value));
    }

    /**
     * Lê o valor atual da propriedade nos dados do recurso.
     * Reads the current property value from the resource data.
     */
    public function value(): mixed
    {
        return $this->currentValue;
    }

    /**
     * Nome da propriedade que este proxy representa.
     * Name of the property this proxy represents.
     */
    public function getPropertyName(): string
    {
        return $this->propertyName;
    }

    public function __toString(): string
    {
        if (is_array($this->currentValue)) {
            return json_encode($this->currentValue) ?: '';
        }

        return (string) ($this->currentValue ?? '');
    }
}
