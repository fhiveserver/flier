<?php

declare(strict_types=1);

use FHIR\Flier\Contracts\FHIRSearchParameter;
use FHIR\Flier\Sources\InMemorySearchParameterSource;

it('returns params registered for a type', function () {
    $source = new InMemorySearchParameterSource([
        'Patient' => [
            new FHIRSearchParameter('name', 'string', 'Patient.name'),
            new FHIRSearchParameter('birthdate', 'date', 'Patient.birthDate'),
        ],
    ]);

    $params = $source->forType('Patient');

    expect($params)->toHaveCount(2);
    expect($params[0]->code)->toBe('name');
    expect($params[1]->code)->toBe('birthdate');
});

it('returns empty array for unknown type', function () {
    $source = new InMemorySearchParameterSource([]);

    expect($source->forType('UnknownType'))->toBe([]);
});

it('can be built with only() named constructor', function () {
    $source = InMemorySearchParameterSource::only(
        resourceType: 'User',
        params: [
            new FHIRSearchParameter('name', 'string', 'User.name'),
        ],
    );

    expect($source->forType('User'))->toHaveCount(1);
    expect($source->forType('Patient'))->toBe([]);
});
