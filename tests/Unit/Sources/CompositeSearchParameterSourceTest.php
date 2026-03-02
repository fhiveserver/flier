<?php

declare(strict_types=1);

use FHIR\Flier\Contracts\FHIRSearchParameter;
use FHIR\Flier\Sources\CompositeSearchParameterSource;
use FHIR\Flier\Sources\InMemorySearchParameterSource;

it('merges params from multiple sources for the same type', function () {
    $source1 = new InMemorySearchParameterSource([
        'Patient' => [new FHIRSearchParameter('name', 'string', 'Patient.name')],
    ]);
    $source2 = new InMemorySearchParameterSource([
        'Patient' => [new FHIRSearchParameter('birthdate', 'date', 'Patient.birthDate')],
    ]);

    $composite = new CompositeSearchParameterSource([$source1, $source2]);

    $params = $composite->forType('Patient');

    expect($params)->toHaveCount(2);
    expect(array_column($params, 'code'))->toBe(['name', 'birthdate']);
});

it('returns empty array when no source knows the type', function () {
    $composite = new CompositeSearchParameterSource([
        new InMemorySearchParameterSource([]),
    ]);

    expect($composite->forType('Unknown'))->toBe([]);
});

it('can add sources dynamically via add()', function () {
    $composite = new CompositeSearchParameterSource([]);

    $composite->add(new InMemorySearchParameterSource([
        'User' => [new FHIRSearchParameter('email', 'string', 'User.email')],
    ]));

    expect($composite->forType('User'))->toHaveCount(1);
});

it('deduplicates params with the same code — first source wins', function () {
    $source1 = new InMemorySearchParameterSource([
        'Patient' => [new FHIRSearchParameter('name', 'string', 'Patient.name.family')],
    ]);
    $source2 = new InMemorySearchParameterSource([
        'Patient' => [new FHIRSearchParameter('name', 'string', 'Patient.name.text')],
    ]);

    $composite = new CompositeSearchParameterSource([$source1, $source2]);
    $params = $composite->forType('Patient');

    expect($params)->toHaveCount(1);
    expect($params[0]->expression)->toBe('Patient.name.family'); // first wins
});
