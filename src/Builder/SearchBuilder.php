<?php

declare(strict_types=1);

namespace FHIR\Flier\Builder;

use FHIR\Flier\Drivers\SearchDriver;
use Illuminate\Support\Traits\Macroable;

/**
 * Builder fluente para buscas FHIR com magic methods.
 * Fluent builder for FHIR searches using magic methods.
 *
 * Parâmetros de busca FHIR viram métodos via __call.
 * A operação search() é método real — executa via driver ou gera URL.
 * Driver é definido via useDriver() — padrão: gera URL.
 *
 * FHIR search parameters become methods via __call.
 * The search() operation is a real method — executes via driver or generates URL.
 * Driver is set via useDriver() — default: generates URL.
 *
 * Convenção de conflito / Conflict convention:
 *   ->searchProperty('value') → parâmetro 'search'
 *
 * Uso / Usage:
 *   // Gera URL para servidor externo / Generates URL for external server
 *   $url = Flier::search('Patient')
 *       ->family('Smith')
 *       ->birthdate('ge1990-01-01')
 *       ->gender('male')
 *       ->search();  // "Patient?family=Smith&birthdate=ge1990-01-01&gender=male"
 *
 *   // Busca no índice local / Searches local index
 *   $ids = Flier::search('Patient')
 *       ->useDriver(IndexSearchDriver::class)
 *       ->family('Smith')
 *       ->search();  // Collection<int, string>
 *
 *   // Com base URL / With base URL
 *   $url = Flier::search('Patient')
 *       ->family('Smith')
 *       ->asUrl('https://hapi.fhir.org/baseR4');
 */
class SearchBuilder
{
    use Macroable { __call as callMacroMethod; }

    private ?SearchDriver $driver = null;

    /** @var list<SearchParam> */
    private array $params = [];

    public function __construct(
        private readonly string $resourceType,
    ) {}

    // ——————————————————————————————————————————————————————————————————
    // Magic method — parâmetros de busca / search parameters
    // ——————————————————————————————————————————————————————————————————

    /**
     * Intercepta parâmetros de busca FHIR como métodos fluentes.
     * Intercepts FHIR search parameters as fluent methods.
     *
     * Conflito / Conflict: ->searchProperty('value') → parâmetro 'search'
     *
     * @param  string  $name  Código do parâmetro / Parameter code
     * @param  array<int, mixed>  $args  [valor, tipo?] / [value, type?]
     */
    public function __call(string $name, array $args): mixed
    {
        // Macros têm prioridade sobre o magic de parâmetros FHIR.
        // Macros take priority over FHIR parameter magic.
        if (static::hasMacro($name)) {
            return $this->callMacroMethod($name, $args);
        }

        // Resolve sufixo de conflito: ->searchProperty() → parâmetro 'search'
        // Resolve conflict suffix: ->searchProperty() → parameter 'search'
        $code = str_ends_with($name, 'Property')
            ? substr($name, 0, -8)
            : $name;

        $value = (string) ($args[0] ?? '');
        $type = isset($args[1]) ? (string) $args[1] : 'string';

        $this->params[] = new SearchParam(
            code: $code,
            type: $type,
            rawValue: $value,
        );

        return $this;
    }

    // ——————————————————————————————————————————————————————————————————
    // Driver / Configuração / Configuration
    // ——————————————————————————————————————————————————————————————————

    /**
     * Define o driver de busca (fluente).
     * Sets the search driver (fluent).
     *
     * @param  string|SearchDriver  $driver  Classe ou instância / Class or instance
     */
    public function useDriver(string|SearchDriver $driver): static
    {
        $this->driver = is_string($driver) ? app($driver) : $driver;

        return $this;
    }

    // ——————————————————————————————————————————————————————————————————
    // Operação de busca / Search operation
    // ——————————————————————————————————————————————————————————————————

    /**
     * Executa a busca com os parâmetros acumulados.
     * Executes the search with the accumulated parameters.
     *
     * Sem driver: retorna a URL da query FHIR (string).
     * Com driver: delega (busca no índice, HTTP, etc.) — retorna o que o driver retornar.
     *
     * Without driver: returns the FHIR query URL (string).
     * With driver: delegates (index search, HTTP, etc.) — returns whatever the driver returns.
     */
    public function search(): mixed
    {
        if ($this->driver !== null) {
            return $this->driver->search($this->resourceType, $this->params);
        }

        return $this->asUrl();
    }

    // ——————————————————————————————————————————————————————————————————
    // Saídas diretas / Direct outputs
    // ——————————————————————————————————————————————————————————————————

    /**
     * Gera a query string FHIR completa (com base URL opcional).
     * Generates the full FHIR query string (with optional base URL).
     *
     * @param  string|null  $baseUrl  URL base do servidor FHIR / FHIR server base URL
     * @return string Exemplo: "Patient?family=Smith&birthdate=ge1990"
     */
    public function asUrl(?string $baseUrl = null): string
    {
        $query = [];

        foreach ($this->params as $param) {
            $key = $param->modifier !== null
                ? "{$param->code}:{$param->modifier}"
                : $param->code;

            $query[$key] = $param->rawValue;
        }

        $queryString = http_build_query($query);
        $path = "{$this->resourceType}?{$queryString}";

        return $baseUrl !== null
            ? rtrim($baseUrl, '/')."/{$path}"
            : $path;
    }

    // ——————————————————————————————————————————————————————————————————
    // Accessors internos / Internal accessors
    // ——————————————————————————————————————————————————————————————————

    /** @return list<SearchParam> */
    public function getParams(): array
    {
        return $this->params;
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }
}
