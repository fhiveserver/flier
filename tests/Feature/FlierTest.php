<?php

declare(strict_types=1);

use FHIVE\Flier\Builder\ResourceBuilder;
use FHIVE\Flier\Builder\SearchBuilder;
use FHIVE\Flier\Flier;

// ————————————————————————————————————————————————————————————————
// Flier::resource()
// ————————————————————————————————————————————————————————————————

it('creates a ResourceBuilder with resourceType set', function (): void {
    $builder = Flier::resource('Patient');

    expect($builder)->toBeInstanceOf(ResourceBuilder::class)
        ->and($builder->getResourceType())->toBe('Patient')
        ->and($builder->getData())->toBe(['resourceType' => 'Patient']);
});

it('creates a ResourceBuilder with existing data', function (): void {
    $data = ['resourceType' => 'Patient', 'id' => fake()->uuid(), 'gender' => 'male'];

    $builder = Flier::resource('Patient', $data);

    expect($builder->getData())->toBe($data);
});

it('uses existing data as-is when non-empty', function (): void {
    $data = ['resourceType' => 'Patient', 'custom' => 'value'];

    $builder = Flier::resource('Patient', $data);

    // When data is non-empty, it should use it as-is (not overwrite resourceType)
    expect($builder->getData())->toBe($data);
});

// ————————————————————————————————————————————————————————————————
// Flier::search()
// ————————————————————————————————————————————————————————————————

it('creates a SearchBuilder', function (): void {
    $builder = Flier::search('Patient');

    expect($builder)->toBeInstanceOf(SearchBuilder::class)
        ->and($builder->getResourceType())->toBe('Patient');
});

it('creates a SearchBuilder and generates URL', function (): void {
    $url = Flier::search('Patient')
        ->family('Smith')
        ->gender('male')
        ->asUrl();

    expect($url)->toContain('Patient?')
        ->and($url)->toContain('family=Smith')
        ->and($url)->toContain('gender=male');
});

// ————————————————————————————————————————————————————————————————
// Flier::from()
// ————————————————————————————————————————————————————————————————

it('creates a ResourceBuilder from an existing array', function (): void {
    $data = [
        'resourceType' => 'Patient',
        'id' => fake()->uuid(),
        'gender' => 'male',
    ];

    $builder = Flier::from($data);

    expect($builder)->toBeInstanceOf(ResourceBuilder::class)
        ->and($builder->getResourceType())->toBe('Patient')
        ->and($builder->getData())->toBe($data);
});

it('throws InvalidArgumentException when resourceType is missing', function (): void {
    Flier::from(['id' => fake()->uuid(), 'gender' => 'male']);
})->throws(InvalidArgumentException::class, "Array must contain 'resourceType'.");

it('allows editing a resource created via from()', function (): void {
    $data = [
        'resourceType' => 'Patient',
        'id' => fake()->uuid(),
        'gender' => 'male',
        'active' => true,
    ];

    $result = Flier::from($data)
        ->gender()->replace('female')
        ->active()->delete()
        ->birthDate('1990-01-15')
        ->toArray();

    expect($result['gender'])->toBe('female')
        ->and($result)->not->toHaveKey('active')
        ->and($result['birthDate'])->toBe('1990-01-15');
});

// ————————————————————————————————————————————————————————————————
// Flier::fromBundle()
// ————————————————————————————————————————————————————————————————

it('creates ResourceBuilders from a FHIR Bundle', function (): void {
    $bundle = [
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'entry' => [
            [
                'fullUrl' => 'https://example.com/Patient/1',
                'resource' => [
                    'resourceType' => 'Patient',
                    'id' => 'p1',
                    'gender' => 'male',
                ],
            ],
            [
                'fullUrl' => 'https://example.com/Patient/2',
                'resource' => [
                    'resourceType' => 'Patient',
                    'id' => 'p2',
                    'gender' => 'female',
                ],
            ],
        ],
    ];

    $builders = Flier::fromBundle($bundle);

    expect($builders)->toHaveCount(2)
        ->and($builders[0])->toBeInstanceOf(ResourceBuilder::class)
        ->and($builders[0]->getResourceType())->toBe('Patient')
        ->and($builders[0]->getData()['id'])->toBe('p1')
        ->and($builders[1]->getData()['id'])->toBe('p2');
});

it('skips entries without resource in a Bundle', function (): void {
    $bundle = [
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'entry' => [
            [
                'fullUrl' => 'https://example.com/Patient/1',
                'resource' => [
                    'resourceType' => 'Patient',
                    'id' => 'p1',
                ],
            ],
            [
                'fullUrl' => 'https://example.com/Patient/deleted',
                // No 'resource' key
            ],
            [
                'fullUrl' => 'https://example.com/Patient/3',
                'resource' => [
                    'resourceType' => 'Patient',
                    'id' => 'p3',
                ],
            ],
        ],
    ];

    $builders = Flier::fromBundle($bundle);

    expect($builders)->toHaveCount(2)
        ->and($builders[0]->getData()['id'])->toBe('p1')
        ->and($builders[1]->getData()['id'])->toBe('p3');
});

it('returns empty collection for a Bundle with no entries', function (): void {
    $bundle = [
        'resourceType' => 'Bundle',
        'type' => 'searchset',
    ];

    $builders = Flier::fromBundle($bundle);

    expect($builders)->toHaveCount(0);
});

it('returns empty collection for a Bundle with empty entries array', function (): void {
    $bundle = [
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'entry' => [],
    ];

    $builders = Flier::fromBundle($bundle);

    expect($builders)->toHaveCount(0);
});

it('allows editing resources from a parsed Bundle', function (): void {
    $bundle = [
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'entry' => [
            [
                'resource' => [
                    'resourceType' => 'Patient',
                    'id' => 'p1',
                    'gender' => 'male',
                ],
            ],
        ],
    ];

    $builders = Flier::fromBundle($bundle);

    $result = $builders[0]
        ->gender()->replace('female')
        ->active(true)
        ->toArray();

    expect($result['gender'])->toBe('female')
        ->and($result['active'])->toBeTrue()
        ->and($result['id'])->toBe('p1');
});

// ————————————————————————————————————————————————————————————————
// End-to-end fluent scenarios
// ————————————————————————————————————————————————————————————————

it('builds a Patient resource end-to-end via Flier facade', function (): void {
    $result = Flier::resource('Patient')
        ->id(fake()->uuid())
        ->name([['family' => 'Doe', 'given' => ['Jane']]])
        ->gender('female')
        ->birthDate('1985-05-20')
        ->active(true)
        ->toArray();

    expect($result['resourceType'])->toBe('Patient')
        ->and($result['name'][0]['family'])->toBe('Doe')
        ->and($result['gender'])->toBe('female')
        ->and($result['birthDate'])->toBe('1985-05-20')
        ->and($result['active'])->toBeTrue();
});

it('generates a FHIRPath Patch via Flier facade', function (): void {
    $existing = [
        'resourceType' => 'Patient',
        'id' => fake()->uuid(),
        'birthDate' => '1990-01-15',
        'gender' => 'male',
    ];

    $patch = Flier::resource('Patient', $existing)
        ->birthDate()->delete()
        ->gender()->replace('female')
        ->active(true)
        ->asFHIRPatch();

    expect($patch['resourceType'])->toBe('Parameters')
        ->and($patch['parameter'])->toHaveCount(3);

    // Operations should be delete, replace, add (in order)
    $types = array_map(
        fn (array $p) => $p['part'][0]['valueCode'],
        $patch['parameter'],
    );

    expect($types)->toBe(['delete', 'replace', 'add']);
});
