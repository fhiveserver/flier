<?php

declare(strict_types=1);

namespace FHIR\Flier;

use FHIR\Flier\Contracts\FHIRSearchParameterSource;
use FHIR\Flier\Patch\FHIRPathPatchDriver;
use FHIR\Flier\Sources\CompositeSearchParameterSource;
use Illuminate\Support\ServiceProvider;

/**
 * Provedor de serviços do módulo Flier — SDK FHIR open-source (builders, drivers, macros).
 * Service provider for the Flier module — open-source FHIR SDK (builders, drivers, macros).
 */
class FlierServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // CompositeSearchParameterSource: singleton — lista de fontes imutável após o boot.
        // CompositeSearchParameterSource: singleton — source list is immutable after boot.
        $this->app->singleton(CompositeSearchParameterSource::class);

        // FHIRSearchParameterSource resolve para o composite — fontes registradas via Flier::registerSearchParameterSource().
        // FHIRSearchParameterSource resolves to the composite — sources registered via Flier::registerSearchParameterSource().
        $this->app->bind(FHIRSearchParameterSource::class, CompositeSearchParameterSource::class);

        // FHIRPathPatchDriver: singleton — sem estado, puro.
        // FHIRPathPatchDriver: singleton — stateless, pure.
        $this->app->singleton(FHIRPathPatchDriver::class);
    }

    public function boot(): void {}
}
