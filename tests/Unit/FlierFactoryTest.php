<?php

declare(strict_types=1);

use FHIR\Flier\Builder\ResourceBuilder;
use FHIR\Flier\Flier;

// — from() ————————————————————————————————————————————————————————————

it('from() creates ResourceBuilder from FHIR resource array', function () {
    $data = ['resourceType' => 'Patient', 'id' => 'p1', 'gender' => 'male'];

    $builder = Flier::from($data);

    expect($builder)->toBeInstanceOf(ResourceBuilder::class);
    expect($builder->getResourceType())->toBe('Patient');
    expect($builder->getData()['id'])->toBe('p1');
});

it('from() preserves all existing properties in data', function () {
    $data = [
        'resourceType' => 'Observation',
        'id' => 'obs-1',
        'status' => 'final',
        'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '8867-4']]],
    ];

    $builder = Flier::from($data);

    expect($builder->getData())->toBe($data);
});

it('from() throws InvalidArgumentException when resourceType is missing', function () {
    expect(fn () => Flier::from(['id' => 'p1']))
        ->toThrow(InvalidArgumentException::class);
});

// — fromBundle() ——————————————————————————————————————————————————————

it('fromBundle() returns a Collection of ResourceBuilders', function () {
    $bundle = [
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'entry' => [
            ['resource' => ['resourceType' => 'Patient', 'id' => 'p1']],
            ['resource' => ['resourceType' => 'Patient', 'id' => 'p2']],
        ],
    ];

    $builders = Flier::fromBundle($bundle);

    expect($builders)->toHaveCount(2);
    expect($builders->first())->toBeInstanceOf(ResourceBuilder::class);
    expect($builders->first()->getResourceType())->toBe('Patient');
});

it('fromBundle() skips entries without resource', function () {
    $bundle = [
        'resourceType' => 'Bundle',
        'entry' => [
            ['resource' => ['resourceType' => 'Patient', 'id' => 'p1']],
            ['fullUrl' => 'urn:uuid:x'],   // sem resource
            ['resource' => ['resourceType' => 'Patient', 'id' => 'p3']],
        ],
    ];

    $builders = Flier::fromBundle($bundle);

    expect($builders)->toHaveCount(2);
});

it('fromBundle() returns empty Collection for bundle without entry', function () {
    $builders = Flier::fromBundle(['resourceType' => 'Bundle', 'type' => 'searchset']);

    expect($builders)->toHaveCount(0);
});
