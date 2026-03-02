<?php

declare(strict_types=1);

namespace FHIR\Flier\Builder\Operations;

/**
 * Representa uma operação pendente sobre um recurso FHIR.
 * Represents a pending operation on a FHIR resource.
 */
interface Operation
{
    /**
     * Nome da propriedade FHIR alvo.
     * Name of the target FHIR property.
     */
    public function getProperty(): string;

    /**
     * Tipo da operação: add | replace | delete.
     * Operation type: add | replace | delete.
     */
    public function getType(): string;
}
