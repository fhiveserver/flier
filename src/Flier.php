<?php

declare(strict_types=1);

namespace FHIR\Flier;

use FHIR\Flier\Builder\ResourceBuilder;
use FHIR\Flier\Builder\SearchBuilder;
use FHIR\Flier\Contracts\FHIRSearchParameterSource;
use FHIR\Flier\Sources\CompositeSearchParameterSource;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Ponto de entrada estático para o Flier — motor FHIR fluente.
 * Static entry point for Flier — fluent FHIR engine.
 *
 * Uso / Usage:
 *
 *   // Construir e editar recursos / Build and edit resources
 *   $resource = Flier::resource('Patient')
 *       ->name([['family' => 'Smith', 'given' => ['John']]])
 *       ->birthDate('1990-01-15')
 *       ->gender('male')
 *       ->toArray();
 *
 *   // Gerar FHIRPath Patch a partir de operações fluentes
 *   // Generate FHIRPath Patch from fluent operations
 *   $patch = Flier::resource('Patient', $existing)
 *       ->birthDate()->delete()
 *       ->status()->replace('active')
 *       ->asFHIRPatch();
 *
 *   // Buscar — gera URL para servidor externo
 *   // Search — generates URL for external server
 *   $url = Flier::search('Patient')
 *       ->family('Smith')
 *       ->birthdate('ge1990-01-01')
 *       ->asUrl('https://hapi.fhir.org/baseR4');
 */
final class Flier
{
    /**
     * Inicia um builder fluente para um recurso FHIR.
     * Starts a fluent builder for a FHIR resource.
     *
     * @param  array<string, mixed>  $data  Dados existentes (edição) / Existing data (for editing)
     */
    public static function resource(string $resourceType, array $data = []): ResourceBuilder
    {
        if (empty($data)) {
            $data = ['resourceType' => $resourceType];
        }

        return new ResourceBuilder($resourceType, $data);
    }

    /**
     * Inicia um builder fluente de busca FHIR.
     * Starts a fluent FHIR search builder.
     */
    public static function search(string $resourceType): SearchBuilder
    {
        return new SearchBuilder($resourceType);
    }

    /**
     * Cria um ResourceBuilder a partir de um recurso FHIR existente (array).
     * Creates a ResourceBuilder from an existing FHIR resource array.
     *
     * Útil para parsear respostas de servidores externos.
     * Useful for parsing responses from external servers.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException se resourceType estiver ausente / if resourceType is missing
     */
    public static function from(array $data): ResourceBuilder
    {
        $resourceType = $data['resourceType']
            ?? throw new InvalidArgumentException("Array must contain 'resourceType'.");

        return new ResourceBuilder((string) $resourceType, $data);
    }

    /**
     * Registra uma fonte de SearchParameters no composite global do Flier.
     * Registers a SearchParameter source in Flier's global composite.
     *
     * Chamado pelos ServiceProviders dos módulos durante o boot — nunca durante requests.
     * Called by module ServiceProviders during boot — never during requests.
     *
     * FrankenPHP-safe: o composite é singleton imutável após o boot.
     * FrankenPHP-safe: the composite is an immutable singleton after boot.
     */
    public static function registerSearchParameterSource(FHIRSearchParameterSource $source): void
    {
        app(CompositeSearchParameterSource::class)->add($source);
    }

    /**
     * Cria um Collection de ResourceBuilders a partir de um FHIR Bundle.
     * Creates a Collection of ResourceBuilders from a FHIR Bundle.
     *
     * Parseia entry[].resource e ignora entradas sem resource.
     * Parses entry[].resource and skips entries without resource.
     *
     * @param  array<string, mixed>  $bundle
     * @return Collection<int, ResourceBuilder>
     */
    public static function fromBundle(array $bundle): Collection
    {
        return collect($bundle['entry'] ?? [])
            ->map(fn (array $entry) => $entry['resource'] ?? null)
            ->filter()
            ->values()
            ->map(fn (array $resource) => self::from($resource));
    }
}
