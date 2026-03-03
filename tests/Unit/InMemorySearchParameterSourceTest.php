<?php

declare(strict_types=1);

use FHIR\Flier\Contracts\FHIRSearchParameter;
use FHIR\Flier\Contracts\FHIRSearchParameterSource;
use FHIR\Flier\Sources\InMemorySearchParameterSource;

it('implements FHIRSearchParameterSource interface', function (): void {
    $source = new InMemorySearchParameterSource;

    expect($source)->toBeInstanceOf(FHIRSearchParameterSource::class);
});

it('returns empty array for unknown resource type', function (): void {
    $source = new InMemorySearchParameterSource;

    expect($source->forType('Patient'))->toBe([]);
});

it('returns parameters for a registered resource type', function (): void {
    $params = [
        new FHIRSearchParameter(code: 'name', type: 'string', expression: 'Patient.name'),
        new FHIRSearchParameter(code: 'birthdate', type: 'date', expression: 'Patient.birthDate'),
    ];

    $source = new InMemorySearchParameterSource(['Patient' => $params]);

    expect($source->forType('Patient'))->toBe($params);
});

it('returns empty array for an unregistered resource type', function (): void {
    $params = [
        new FHIRSearchParameter(code: 'name', type: 'string', expression: 'Patient.name'),
    ];

    $source = new InMemorySearchParameterSource(['Patient' => $params]);

    expect($source->forType('Observation'))->toBe([]);
});

it('creates source for a single type via only()', function (): void {
    $params = [
        new FHIRSearchParameter(code: 'code', type: 'token', expression: 'Observation.code'),
    ];

    $source = InMemorySearchParameterSource::only('Observation', $params);

    expect($source->forType('Observation'))->toBe($params)
        ->and($source->forType('Patient'))->toBe([]);
});

it('supports multiple resource types', function (): void {
    $patientParams = [
        new FHIRSearchParameter(code: 'name', type: 'string', expression: 'Patient.name'),
    ];
    $observationParams = [
        new FHIRSearchParameter(code: 'code', type: 'token', expression: 'Observation.code'),
    ];

    $source = new InMemorySearchParameterSource([
        'Patient' => $patientParams,
        'Observation' => $observationParams,
    ]);

    expect($source->forType('Patient'))->toBe($patientParams)
        ->and($source->forType('Observation'))->toBe($observationParams);
});
