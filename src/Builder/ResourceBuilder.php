<?php

declare(strict_types=1);

namespace FHIR\Flier\Builder;

use FHIR\Flier\Builder\Operations\AddOperation;
use FHIR\Flier\Builder\Operations\Operation;
use FHIR\FlierStorage\Contracts\FHIRIndexedResource;
use FHIR\Flier\Drivers\ArrayResourceDriver;
use FHIR\Flier\Drivers\ResourceDriver;
use FHIR\Flier\Indexer\SearchIndexer;
use FHIR\Flier\Patch\FHIRPathPatchDriver;
use Illuminate\Support\Traits\Macroable;

/**
 * Builder fluente para recursos FHIR com magic methods.
 * Fluent builder for FHIR resources using magic methods.
 *
 * Propriedades FHIR viram métodos via __call.
 * Operações FHIR (create, update, put, delete, index) são métodos reais.
 * Driver é definido via useDriver() — padrão: operações em memória.
 *
 * FHIR properties become methods via __call.
 * FHIR operations (create, update, put, delete, index) are real methods.
 * Driver is set via useDriver() — default: in-memory operations.
 *
 * Convenção de conflito / Conflict convention:
 *   Se uma propriedade FHIR tiver o mesmo nome de um método real,
 *   adicione o sufixo "Property": ->deleteProperty('value') → define 'delete'.
 *   If a FHIR property has the same name as a real method,
 *   add the "Property" suffix: ->deleteProperty('value') → sets 'delete'.
 *
 * Métodos reservados / Reserved methods:
 *   create, update, put, delete, index, useDriver,
 *   toArray, asFHIRPatch, addOperation, getData, getOperations, getResourceType
 *
 * Uso / Usage:
 *   Flier::resource('Patient')
 *       ->name([['family' => 'Smith']])
 *       ->birthDate('1990-01-15')
 *       ->gender('male')
 *       ->create();                        // retorna toArray() sem driver
 *
 *   Flier::resource('Patient', $data)
 *       ->birthDate()->delete()
 *       ->status()->replace('inactive')
 *       ->useDriver(new MyFhirHttpDriver())
 *       ->update();                        // envia FHIRPath Patch via HTTP
 *
 *   Flier::resource('Patient', $data)
 *       ->id('p1')
 *       ->name([['family' => 'Doe']])
 *       ->index();                         // indexa no search_indexes
 */
class ResourceBuilder
{
    use Macroable { __call as callMacroMethod; }

    private ?ResourceDriver $driver = null;

    /** @var list<Operation> */
    private array $operations = [];

    /**
     * @param  array<string, mixed>  $data  Dados atuais do recurso / Current resource data
     */
    public function __construct(
        private readonly string $resourceType,
        private array $data = [],
    ) {}

    // ——————————————————————————————————————————————————————————————————
    // Magic method — propriedades FHIR / FHIR properties
    // ——————————————————————————————————————————————————————————————————

    /**
     * Intercepta propriedades FHIR como métodos fluentes.
     * Intercepts FHIR properties as fluent methods.
     *
     * Sem argumentos  → PropertyProxy (para ->delete(), ->replace(), ->value())
     * Com argumento   → AddOperation + retorna $this (chain)
     *
     * No arguments  → PropertyProxy (for ->delete(), ->replace(), ->value())
     * With argument → AddOperation + returns $this (chain)
     *
     * Conflito / Conflict: ->deleteProperty('x') → define a propriedade 'delete'
     *
     * @param  string  $name  Nome da propriedade FHIR / FHIR property name
     * @param  array<int, mixed>  $args
     */
    public function __call(string $name, array $args): mixed
    {
        // Macros têm prioridade sobre o magic de propriedades FHIR.
        // Macros take priority over FHIR property magic.
        if (static::hasMacro($name)) {
            return $this->callMacroMethod($name, $args);
        }

        // Resolve sufixo de conflito: ->deleteProperty() → propriedade 'delete'
        // Resolve conflict suffix: ->deleteProperty() → property 'delete'
        $property = str_ends_with($name, 'Property')
            ? substr($name, 0, -8)
            : $name;

        if (empty($args)) {
            return new PropertyProxy($property, $this->data[$property] ?? null, $this);
        }

        $this->operations[] = new AddOperation($property, $args[0]);

        return $this;
    }

    // ——————————————————————————————————————————————————————————————————
    // Driver / Configuração / Configuration
    // ——————————————————————————————————————————————————————————————————

    /**
     * Define o driver de operações (fluente).
     * Sets the operation driver (fluent).
     *
     * @param  string|ResourceDriver  $driver  Classe ou instância / Class or instance
     */
    public function useDriver(string|ResourceDriver $driver): static
    {
        $this->driver = is_string($driver) ? app($driver) : $driver;

        return $this;
    }

    // ——————————————————————————————————————————————————————————————————
    // Operações FHIR / FHIR Operations
    // ——————————————————————————————————————————————————————————————————

    /**
     * Cria o recurso (POST semântico).
     * Creates the resource (POST semantics).
     *
     * Sem driver: aplica operações e retorna array PHP.
     * Com driver: delega ao driver (HTTP POST, DB insert, etc.).
     *
     * Without driver: applies operations and returns PHP array.
     * With driver: delegates to driver (HTTP POST, DB insert, etc.).
     */
    public function create(): mixed
    {
        if ($this->driver !== null) {
            return $this->driver->create($this->resourceType, $this->data, $this->operations);
        }

        return $this->toArray();
    }

    /**
     * Atualiza o recurso com as operações acumuladas (PATCH semântico).
     * Updates the resource with accumulated operations (PATCH semantics).
     *
     * Sem driver: aplica operações e retorna array PHP.
     * Com driver: delega ao driver (FHIRPath Patch, HTTP PATCH, DB update, etc.).
     *
     * Without driver: applies operations and returns PHP array.
     * With driver: delegates to driver (FHIRPath Patch, HTTP PATCH, DB update, etc.).
     */
    public function update(): mixed
    {
        if ($this->driver !== null) {
            return $this->driver->update($this->resourceType, $this->data, $this->operations);
        }

        return $this->toArray();
    }

    /**
     * Substitui o recurso inteiro (PUT semântico).
     * Replaces the entire resource (PUT semantics).
     *
     * Sem driver: aplica operações e retorna array PHP.
     * Com driver: delega ao driver (HTTP PUT, DB upsert, etc.).
     *
     * Without driver: applies operations and returns PHP array.
     * With driver: delegates to driver (HTTP PUT, DB upsert, etc.).
     */
    public function put(): mixed
    {
        if ($this->driver !== null) {
            return $this->driver->put($this->resourceType, $this->data, $this->operations);
        }

        return $this->toArray();
    }

    /**
     * Remove o recurso (DELETE semântico).
     * Deletes the resource (DELETE semantics).
     *
     * Sem driver: retorna array vazio.
     * Com driver: delega ao driver (HTTP DELETE, DB delete, etc.).
     *
     * Without driver: returns empty array.
     * With driver: delegates to driver (HTTP DELETE, DB delete, etc.).
     *
     * Nota: ->property()->delete() remove a propriedade (PropertyProxy::delete()).
     * Note: ->property()->delete() removes a property (PropertyProxy::delete()).
     */
    public function delete(): mixed
    {
        if ($this->driver !== null) {
            return $this->driver->delete($this->resourceType, $this->data);
        }

        return [];
    }

    /**
     * Indexa o recurso no motor de busca local (search_indexes).
     * Indexes the resource in the local search engine (search_indexes).
     *
     * Aplica operações pendentes antes de indexar.
     * Applies pending operations before indexing.
     *
     * Requer 'id' nos dados do recurso / Requires 'id' in resource data.
     */
    public function index(): static
    {
        $data = $this->toArray();
        $id = $data['id'] ?? null;

        if ($id === null) {
            return $this;
        }

        $type = $this->resourceType;

        $resource = new class((string) $id, $type, $data) implements FHIRIndexedResource
        {
            /**
             * @param  array<string, mixed>  $resourceData
             */
            public function __construct(
                private readonly string $resourceId,
                private readonly string $resourceType,
                private readonly array $resourceData,
            ) {}

            public function getResourceId(): string
            {
                return $this->resourceId;
            }

            public function getResourceType(): string
            {
                return $this->resourceType;
            }

            /**
             * @return array<string, mixed>
             */
            public function getResourceData(): array
            {
                return $this->resourceData;
            }
        };

        app(SearchIndexer::class)->index($resource);

        return $this;
    }

    // ——————————————————————————————————————————————————————————————————
    // Saídas diretas / Direct outputs (sem driver / without driver)
    // ——————————————————————————————————————————————————————————————————

    /**
     * Aplica operações e retorna o recurso como array PHP.
     * Applies operations and returns the resource as a PHP array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return (new ArrayResourceDriver)->apply($this->data, $this->operations);
    }

    /**
     * Converte operações para FHIRPath Patch (Parameters resource).
     * Converts operations to FHIRPath Patch (Parameters resource).
     *
     * @return array<string, mixed>
     */
    public function asFHIRPatch(): array
    {
        return app(FHIRPathPatchDriver::class)->generate($this->resourceType, $this->operations);
    }

    // ——————————————————————————————————————————————————————————————————
    // Accessors internos / Internal accessors
    // ——————————————————————————————————————————————————————————————————

    /**
     * Adiciona uma operação (chamado por PropertyProxy).
     * Adds an operation (called by PropertyProxy).
     */
    public function addOperation(Operation $op): static
    {
        $this->operations[] = $op;

        return $this;
    }

    /** @return list<Operation> */
    public function getOperations(): array
    {
        return $this->operations;
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        return $this->data;
    }
}
