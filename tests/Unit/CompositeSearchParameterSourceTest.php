<?php

declare(strict_types=1);

use FHIVE\Flier\Contracts\FHIRSearchParameter;
use FHIVE\Flier\Contracts\FHIRSearchParameterSource;
use FHIVE\Flier\Sources\CompositeSearchParameterSource;
use FHIVE\Flier\Sources\InMemorySearchParameterSource;

it('implements FHIRSearchParameterSource interface', function (): void {
    $composite = new CompositeSearchParameterSource;

    expect($composite)->toBeInstanceOf(FHIRSearchParameterSource::class);
});

it('returns empty array when no sources are registered', function (): void {
    $composite = new CompositeSearchParameterSource;

    expect($composite->forType('Patient'))->toBe([]);
});

it('merges parameters from a single source', function (): void {
    $params = [
        new FHIRSearchParameter(code: 'name', type: 'string', expression: 'Patient.name'),
    ];

    $composite = new CompositeSearchParameterSource;
    $composite->add(InMemorySearchParameterSource::only('Patient', $params));

    $result = $composite->forType('Patient');

    expect($result)->toHaveCount(1)
        ->and($result[0]->code)->toBe('name');
});

it('merges parameters from multiple sources', function (): void {
    $source1 = InMemorySearchParameterSource::only('Patient', [
        new FHIRSearchParameter(code: 'name', type: 'string', expression: 'Patient.name'),
    ]);

    $source2 = InMemorySearchParameterSource::only('Patient', [
        new FHIRSearchParameter(code: 'birthdate', type: 'date', expression: 'Patient.birthDate'),
    ]);

    $composite = new CompositeSearchParameterSource;
    $composite->add($source1);
    $composite->add($source2);

    $result = $composite->forType('Patient');

    expect($result)->toHaveCount(2);

    $codes = array_map(fn (FHIRSearchParameter $p) => $p->code, $result);

    expect($codes)->toBe(['name', 'birthdate']);
});

it('deduplicates by code with first source winning', function (): void {
    $source1 = InMemorySearchParameterSource::only('Patient', [
        new FHIRSearchParameter(
            code: 'name',
            type: 'string',
            expression: 'Patient.name',
            description: 'First source',
        ),
    ]);

    $source2 = InMemorySearchParameterSource::only('Patient', [
        new FHIRSearchParameter(
            code: 'name',
            type: 'string',
            expression: 'Patient.name.family',
            description: 'Second source — should be ignored',
        ),
    ]);

    $composite = new CompositeSearchParameterSource;
    $composite->add($source1);
    $composite->add($source2);

    $result = $composite->forType('Patient');

    expect($result)->toHaveCount(1)
        ->and($result[0]->description)->toBe('First source')
        ->and($result[0]->expression)->toBe('Patient.name');
});

it('returns only parameters for the requested resource type', function (): void {
    $source = new InMemorySearchParameterSource([
        'Patient' => [
            new FHIRSearchParameter(code: 'name', type: 'string', expression: 'Patient.name'),
        ],
        'Observation' => [
            new FHIRSearchParameter(code: 'code', type: 'token', expression: 'Observation.code'),
        ],
    ]);

    $composite = new CompositeSearchParameterSource;
    $composite->add($source);

    $patientResult = $composite->forType('Patient');
    $observationResult = $composite->forType('Observation');

    expect($patientResult)->toHaveCount(1)
        ->and($patientResult[0]->code)->toBe('name')
        ->and($observationResult)->toHaveCount(1)
        ->and($observationResult[0]->code)->toBe('code');
});

it('accepts sources via constructor', function (): void {
    $source = InMemorySearchParameterSource::only('Patient', [
        new FHIRSearchParameter(code: 'name', type: 'string', expression: 'Patient.name'),
    ]);

    $composite = new CompositeSearchParameterSource([$source]);

    expect($composite->forType('Patient'))->toHaveCount(1);
});
