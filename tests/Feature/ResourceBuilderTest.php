<?php

declare(strict_types=1);

use FHIR\Flier\Builder\Operations\AddOperation;
use FHIR\Flier\Builder\Operations\DeleteOperation;
use FHIR\Flier\Builder\Operations\ReplaceOperation;
use FHIR\Flier\Builder\PropertyProxy;
use FHIR\Flier\Builder\ResourceBuilder;
use FHIR\Flier\Drivers\ArrayResourceDriver;
use FHIR\Flier\Drivers\ResourceDriver;

// ————————————————————————————————————————————————————————————————
// Constructor & Accessors
// ————————————————————————————————————————————————————————————————

it('stores resource type and data from constructor', function (): void {
    $builder = new ResourceBuilder('Patient', ['resourceType' => 'Patient', 'id' => 'p1']);

    expect($builder->getResourceType())->toBe('Patient')
        ->and($builder->getData())->toBe(['resourceType' => 'Patient', 'id' => 'p1']);
});

it('starts with empty operations', function (): void {
    $builder = new ResourceBuilder('Patient');

    expect($builder->getOperations())->toBe([]);
});

// ————————————————————————————————————————————————————————————————
// Magic __call — property setting (with value)
// ————————————————————————————————————————————————————————————————

it('creates AddOperation when calling property with a value', function (): void {
    $builder = new ResourceBuilder('Patient', ['resourceType' => 'Patient']);
    $returned = $builder->gender('male');

    expect($returned)->toBe($builder);

    $ops = $builder->getOperations();
    expect($ops)->toHaveCount(1)
        ->and($ops[0])->toBeInstanceOf(AddOperation::class)
        ->and($ops[0]->getProperty())->toBe('gender')
        ->and($ops[0]->getValue())->toBe('male');
});

it('chains multiple property calls', function (): void {
    $builder = new ResourceBuilder('Patient', ['resourceType' => 'Patient']);

    $builder
        ->gender('male')
        ->birthDate('1990-01-15')
        ->active(true);

    $ops = $builder->getOperations();
    expect($ops)->toHaveCount(3);

    $properties = array_map(fn ($op) => $op->getProperty(), $ops);
    expect($properties)->toBe(['gender', 'birthDate', 'active']);
});

// ————————————————————————————————————————————————————————————————
// Magic __call — property proxy (without value)
// ————————————————————————————————————————————————————————————————

it('returns PropertyProxy when calling property without arguments', function (): void {
    $builder = new ResourceBuilder('Patient', [
        'resourceType' => 'Patient',
        'gender' => 'male',
    ]);

    $proxy = $builder->gender();

    expect($proxy)->toBeInstanceOf(PropertyProxy::class)
        ->and($proxy->value())->toBe('male')
        ->and($proxy->getPropertyName())->toBe('gender');
});

it('returns PropertyProxy with null value for missing property', function (): void {
    $builder = new ResourceBuilder('Patient', ['resourceType' => 'Patient']);

    $proxy = $builder->birthDate();

    expect($proxy)->toBeInstanceOf(PropertyProxy::class)
        ->and($proxy->value())->toBeNull();
});

// ————————————————————————————————————————————————————————————————
// Conflict suffix (Property)
// ————————————————————————————————————————————————————————————————

it('resolves conflict suffix by stripping Property from method name', function (): void {
    $builder = new ResourceBuilder('Task', ['resourceType' => 'Task']);

    // ->deleteProperty('x') should set the FHIR property 'delete', not call delete()
    $builder->deleteProperty('some-value');

    $ops = $builder->getOperations();
    expect($ops)->toHaveCount(1)
        ->and($ops[0]->getProperty())->toBe('delete');
});

// ————————————————————————————————————————————————————————————————
// toArray()
// ————————————————————————————————————————————————————————————————

it('returns original data when no operations exist', function (): void {
    $data = ['resourceType' => 'Patient', 'gender' => 'male'];
    $builder = new ResourceBuilder('Patient', $data);

    expect($builder->toArray())->toBe($data);
});

it('applies operations and returns final array', function (): void {
    $builder = new ResourceBuilder('Patient', ['resourceType' => 'Patient']);
    $builder->gender('male');
    $builder->birthDate('1990-01-15');

    $result = $builder->toArray();

    expect($result)->toBe([
        'resourceType' => 'Patient',
        'gender' => 'male',
        'birthDate' => '1990-01-15',
    ]);
});

it('applies delete via proxy in toArray', function (): void {
    $builder = new ResourceBuilder('Patient', [
        'resourceType' => 'Patient',
        'gender' => 'male',
        'birthDate' => '1990-01-15',
    ]);

    $builder->birthDate()->delete();

    $result = $builder->toArray();

    expect($result)->not->toHaveKey('birthDate')
        ->and($result)->toHaveKey('gender');
});

it('applies replace via proxy in toArray', function (): void {
    $builder = new ResourceBuilder('Patient', [
        'resourceType' => 'Patient',
        'gender' => 'male',
    ]);

    $builder->gender()->replace('female');

    $result = $builder->toArray();

    expect($result['gender'])->toBe('female');
});

// ————————————————————————————————————————————————————————————————
// create / update / put / delete — without driver
// ————————————————————————————————————————————————————————————————

it('create without driver returns toArray result', function (): void {
    $builder = new ResourceBuilder('Patient', ['resourceType' => 'Patient']);
    $builder->gender('male');

    $result = $builder->create();

    expect($result)->toBe($builder->toArray());
});

it('update without driver returns toArray result', function (): void {
    $builder = new ResourceBuilder('Patient', [
        'resourceType' => 'Patient',
        'gender' => 'male',
    ]);
    $builder->gender()->replace('female');

    $result = $builder->update();

    expect($result['gender'])->toBe('female');
});

it('put without driver returns toArray result', function (): void {
    $builder = new ResourceBuilder('Patient', ['resourceType' => 'Patient']);
    $builder->active(true);

    $result = $builder->put();

    expect($result['active'])->toBeTrue();
});

it('delete without driver returns empty array', function (): void {
    $builder = new ResourceBuilder('Patient', [
        'resourceType' => 'Patient',
        'id' => fake()->uuid(),
    ]);

    $result = $builder->delete();

    expect($result)->toBe([]);
});

// ————————————————————————————————————————————————————————————————
// create / update / put / delete — with driver
// ————————————————————————————————————————————————————————————————

it('create with driver delegates to driver', function (): void {
    $driver = Mockery::mock(ResourceDriver::class);
    $driver->shouldReceive('create')
        ->once()
        ->withArgs(function (string $type, array $data, array $ops): bool {
            return $type === 'Patient' && count($ops) === 1;
        })
        ->andReturn(['resourceType' => 'Patient', 'id' => 'created-1']);

    $builder = new ResourceBuilder('Patient', ['resourceType' => 'Patient']);
    $builder->gender('male');
    $builder->useDriver($driver);

    $result = $builder->create();

    expect($result['id'])->toBe('created-1');
});

it('update with driver delegates to driver', function (): void {
    $driver = Mockery::mock(ResourceDriver::class);
    $driver->shouldReceive('update')
        ->once()
        ->andReturn(['patched' => true]);

    $builder = new ResourceBuilder('Patient', ['resourceType' => 'Patient']);
    $builder->useDriver($driver);

    $result = $builder->update();

    expect($result['patched'])->toBeTrue();
});

it('put with driver delegates to driver', function (): void {
    $driver = Mockery::mock(ResourceDriver::class);
    $driver->shouldReceive('put')
        ->once()
        ->andReturn(['put' => true]);

    $builder = new ResourceBuilder('Patient', ['resourceType' => 'Patient']);
    $builder->useDriver($driver);

    $result = $builder->put();

    expect($result['put'])->toBeTrue();
});

it('delete with driver delegates to driver', function (): void {
    $driver = Mockery::mock(ResourceDriver::class);
    $driver->shouldReceive('delete')
        ->once()
        ->withArgs(fn (string $type, array $data) => $type === 'Patient')
        ->andReturn([]);

    $builder = new ResourceBuilder('Patient', [
        'resourceType' => 'Patient',
        'id' => fake()->uuid(),
    ]);
    $builder->useDriver($driver);

    $result = $builder->delete();

    expect($result)->toBe([]);
});

// ————————————————————————————————————————————————————————————————
// useDriver() fluent
// ————————————————————————————————————————————————————————————————

it('useDriver returns the builder for chaining', function (): void {
    $driver = new ArrayResourceDriver;
    $builder = new ResourceBuilder('Patient', ['resourceType' => 'Patient']);

    $returned = $builder->useDriver($driver);

    expect($returned)->toBe($builder);
});

// ————————————————————————————————————————————————————————————————
// asFHIRPatch()
// ————————————————————————————————————————————————————————————————

it('generates a FHIRPath Patch Parameters resource', function (): void {
    $builder = new ResourceBuilder('Patient', [
        'resourceType' => 'Patient',
        'birthDate' => '1990-01-15',
    ]);

    $builder->birthDate()->delete();
    $builder->gender('male');

    $patch = $builder->asFHIRPatch();

    expect($patch['resourceType'])->toBe('Parameters')
        ->and($patch['parameter'])->toHaveCount(2);

    // First: delete birthDate
    expect($patch['parameter'][0]['part'][0]['valueCode'])->toBe('delete');

    // Second: add gender
    expect($patch['parameter'][1]['part'][0]['valueCode'])->toBe('add');
});

// ————————————————————————————————————————————————————————————————
// addOperation() directly
// ————————————————————————————————————————————————————————————————

it('addOperation appends to operations list', function (): void {
    $builder = new ResourceBuilder('Patient', ['resourceType' => 'Patient']);

    $builder->addOperation(new DeleteOperation('birthDate'));
    $builder->addOperation(new AddOperation('active', true));

    expect($builder->getOperations())->toHaveCount(2);
});

// ————————————————————————————————————————————————————————————————
// Complex FHIR resource building scenarios
// ————————————————————————————————————————————————————————————————

it('builds a complete Patient resource', function (): void {
    $builder = new ResourceBuilder('Patient', ['resourceType' => 'Patient']);

    $result = $builder
        ->id(fake()->uuid())
        ->name([['family' => 'Smith', 'given' => ['John', 'Jacob']]])
        ->gender('male')
        ->birthDate('1990-01-15')
        ->active(true)
        ->telecom([
            ['system' => 'phone', 'value' => '555-1234', 'use' => 'home'],
            ['system' => 'email', 'value' => 'john@example.com'],
        ])
        ->toArray();

    expect($result['resourceType'])->toBe('Patient')
        ->and($result['gender'])->toBe('male')
        ->and($result['birthDate'])->toBe('1990-01-15')
        ->and($result['active'])->toBeTrue()
        ->and($result['name'][0]['family'])->toBe('Smith')
        ->and($result['name'][0]['given'])->toBe(['John', 'Jacob'])
        ->and($result['telecom'])->toHaveCount(2);
});

it('builds an Observation resource with nested value', function (): void {
    $builder = new ResourceBuilder('Observation', ['resourceType' => 'Observation']);

    $result = $builder
        ->id(fake()->uuid())
        ->status('final')
        ->code([
            'coding' => [
                ['system' => 'http://loinc.org', 'code' => '8867-4', 'display' => 'Heart rate'],
            ],
        ])
        ->valueQuantity([
            'value' => 72,
            'unit' => 'beats/minute',
            'system' => 'http://unitsofmeasure.org',
            'code' => '/min',
        ])
        ->toArray();

    expect($result['resourceType'])->toBe('Observation')
        ->and($result['status'])->toBe('final')
        ->and($result['code']['coding'][0]['code'])->toBe('8867-4')
        ->and($result['valueQuantity']['value'])->toBe(72);
});

it('edits an existing resource by adding and replacing properties', function (): void {
    $existing = [
        'resourceType' => 'Patient',
        'id' => fake()->uuid(),
        'gender' => 'male',
        'birthDate' => '1990-01-15',
        'active' => true,
    ];

    $builder = new ResourceBuilder('Patient', $existing);

    $result = $builder
        ->gender()->replace('female')
        ->birthDate()->delete()
        ->deceasedBoolean(false)
        ->toArray();

    expect($result['gender'])->toBe('female')
        ->and($result)->not->toHaveKey('birthDate')
        ->and($result['deceasedBoolean'])->toBeFalse()
        ->and($result['active'])->toBeTrue()
        ->and($result['id'])->toBe($existing['id']);
});
