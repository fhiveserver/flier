<?php

declare(strict_types=1);

namespace FHIR\Flier\Contracts;

/**
 * Representa um FHIR SearchParameter de forma imutável, sem acoplamento ao banco de dados.
 * Represents a FHIR SearchParameter immutably, without database coupling.
 */
final readonly class FHIRSearchParameter
{
    /**
     * @param  list<string>  $modifier  Modificadores suportados (ex: exact, contains, identifier).
     * @param  list<string>  $target  Tipos FHIR alvo para referências (ex: Patient, Group).
     * @param  list<array<string, mixed>>  $component  Componentes para parâmetros compostos.
     */
    public function __construct(
        public string $code,
        public string $type,
        public string $expression,
        public ?string $description = null,
        public array $modifier = [],
        public array $target = [],
        public array $component = [],
    ) {}
}
