<?php

declare(strict_types=1);

use FHIR\Flier\Builder\Operations\AddOperation;
use FHIR\Flier\Builder\Operations\DeleteOperation;
use FHIR\Flier\Builder\Operations\ReplaceOperation;
use FHIR\Flier\Builder\PropertyProxy;
use FHIR\Flier\Builder\ResourceBuilder;
use FHIR\Flier\Drivers\ResourceDriver;

// — __call sem argumentos → PropertyProxy ————————————————————————————————

it('returns PropertyProxy when called with no arguments', function () {
    $builder = new ResourceBuilder('Patient', ['name' => [['family' => 'Smith']]]);

    $proxy = $builder->name();

    expect($proxy)->toBeInstanceOf(PropertyProxy::class);
});

it('PropertyProxy carries the current property value', function () {
    $builder = new ResourceBuilder('Patient', ['birthDate' => '1990-01-15']);

    expect($builder->birthDate()->value())->toBe('1990-01-15');
});

it('PropertyProxy returns null value for missing property', function () {
    $builder = new ResourceBuilder('Patient');

    expect($builder->gender()->value())->toBeNull();
});

// — __call com argumento → AddOperation + $this ——————————————————————————

it('returns $this when called with an argument (chain)', function () {
    $builder = new ResourceBuilder('Patient');

    expect($builder->gender('male'))->toBe($builder);
});

it('accumulates AddOperation when called with a value', function () {
    $builder = new ResourceBuilder('Patient');
    $builder->name([['family' => 'Doe']]);

    $ops = $builder->getOperations();

    expect($ops)->toHaveCount(1);
    expect($ops[0])->toBeInstanceOf(AddOperation::class);
    expect($ops[0]->getProperty())->toBe('name');
});

it('supports chaining multiple properties', function () {
    $builder = new ResourceBuilder('Patient');
    $result = $builder
        ->name([['family' => 'Doe']])
        ->gender('male')
        ->birthDate('1990-01-15');

    expect($result)->toBe($builder);
    expect($builder->getOperations())->toHaveCount(3);
});

// — Convenção Property — resolução de conflitos ——————————————————————————

it('Property suffix resolves conflict: ->deleteProperty() sets property "delete"', function () {
    $builder = new ResourceBuilder('Patient');
    $builder->deleteProperty('some-value');

    $ops = $builder->getOperations();
    expect($ops[0])->toBeInstanceOf(AddOperation::class);
    expect($ops[0]->getProperty())->toBe('delete');
});

it('Property suffix on proxy: ->createProperty() returns PropertyProxy for "create"', function () {
    $builder = new ResourceBuilder('Patient', ['create' => 'hypothetical']);

    $proxy = $builder->createProperty();

    expect($proxy)->toBeInstanceOf(PropertyProxy::class);
    expect($proxy->getPropertyName())->toBe('create');
    expect($proxy->value())->toBe('hypothetical');
});

// — PropertyProxy::delete() → DeleteOperation ————————————————————————————

it('property proxy delete() accumulates DeleteOperation and returns ResourceBuilder', function () {
    $builder = new ResourceBuilder('Patient', ['birthDate' => '1990-01-15']);

    $result = $builder->birthDate()->delete();

    expect($result)->toBe($builder);
    $ops = $builder->getOperations();
    expect($ops)->toHaveCount(1);
    expect($ops[0])->toBeInstanceOf(DeleteOperation::class);
    expect($ops[0]->getProperty())->toBe('birthDate');
});

it('property proxy delete() chains back to builder for further operations', function () {
    $builder = new ResourceBuilder('Patient', [
        'birthDate' => '1990-01-15',
        'gender' => 'male',
    ]);

    $builder
        ->birthDate()->delete()
        ->gender('female');

    expect($builder->getOperations())->toHaveCount(2);
    expect($builder->getOperations()[0])->toBeInstanceOf(DeleteOperation::class);
    expect($builder->getOperations()[1])->toBeInstanceOf(AddOperation::class);
});

// — PropertyProxy::replace() → ReplaceOperation —————————————————————————

it('property proxy replace() accumulates ReplaceOperation', function () {
    $builder = new ResourceBuilder('Patient', ['status' => 'active']);

    $result = $builder->status()->replace('inactive');

    expect($result)->toBe($builder);
    $ops = $builder->getOperations();
    expect($ops[0])->toBeInstanceOf(ReplaceOperation::class);
    expect($ops[0]->getProperty())->toBe('status');
});

// — ResourceBuilder::delete() — remove o recurso —————————————————————————

it('delete() on builder returns empty array without driver', function () {
    $builder = new ResourceBuilder('Patient', ['resourceType' => 'Patient', 'id' => 'p1']);

    expect($builder->delete())->toBe([]);
});

it('delete() on builder delegates to driver when set', function () {
    $driver = Mockery::mock(ResourceDriver::class);
    $driver->expects('delete')->with('Patient', Mockery::any())->andReturn(['deleted' => true]);

    $result = (new ResourceBuilder('Patient', ['id' => 'p1']))
        ->useDriver($driver)
        ->delete();

    expect($result)->toBe(['deleted' => true]);
});

// — create() ——————————————————————————————————————————————————————————————

it('create() without driver applies operations and returns array', function () {
    $result = (new ResourceBuilder('Patient'))
        ->name([['family' => 'Doe']])
        ->birthDate('1990-01-15')
        ->create();

    expect($result['name'])->toBe([['family' => 'Doe']]);
    expect($result['birthDate'])->toBe('1990-01-15');
});

it('create() delegates to driver when set', function () {
    $driver = Mockery::mock(ResourceDriver::class);
    $driver->expects('create')->andReturn(['id' => 'new-1']);

    $result = (new ResourceBuilder('Patient'))
        ->useDriver($driver)
        ->name([['family' => 'Smith']])
        ->create();

    expect($result)->toBe(['id' => 'new-1']);
});

// — update() ——————————————————————————————————————————————————————————————

it('update() without driver applies operations and returns array', function () {
    $builder = new ResourceBuilder('Patient', ['resourceType' => 'Patient', 'gender' => 'male']);

    $result = $builder->gender()->replace('female')->update();

    expect($result['gender'])->toBe('female');
});

it('update() delegates to driver when set', function () {
    $driver = Mockery::mock(ResourceDriver::class);
    $driver->expects('update')->andReturn(['status' => 'updated']);

    $result = (new ResourceBuilder('Patient', ['id' => 'p1']))
        ->useDriver($driver)
        ->gender('female')
        ->update();

    expect($result)->toBe(['status' => 'updated']);
});

// — put() ——————————————————————————————————————————————————————————————

it('put() without driver returns array with operations applied', function () {
    $result = (new ResourceBuilder('Patient'))
        ->gender('male')
        ->put();

    expect($result['gender'])->toBe('male');
});

// — useDriver() fluente ————————————————————————————————————————————————

it('useDriver() returns $this for chaining', function () {
    $driver = Mockery::mock(ResourceDriver::class);
    $builder = new ResourceBuilder('Patient');

    expect($builder->useDriver($driver))->toBe($builder);
});

// — toArray() — aplica operações —————————————————————————————————————————

it('toArray() adds properties from AddOperations', function () {
    $result = (new ResourceBuilder('Patient'))
        ->name([['family' => 'Doe', 'given' => ['Jane']]])
        ->birthDate('1985-05-20')
        ->gender('female')
        ->toArray();

    expect($result['name'])->toBe([['family' => 'Doe', 'given' => ['Jane']]]);
    expect($result['birthDate'])->toBe('1985-05-20');
    expect($result['gender'])->toBe('female');
});

it('toArray() removes properties from DeleteOperations', function () {
    $result = (new ResourceBuilder('Patient', [
        'resourceType' => 'Patient',
        'birthDate' => '1990-01-15',
        'gender' => 'male',
    ]))
        ->birthDate()->delete()
        ->toArray();

    expect($result)->not->toHaveKey('birthDate');
    expect($result['gender'])->toBe('male');
});

it('toArray() replaces properties from ReplaceOperations', function () {
    $result = (new ResourceBuilder('Patient', ['resourceType' => 'Patient', 'gender' => 'male']))
        ->gender()->replace('female')
        ->toArray();

    expect($result['gender'])->toBe('female');
});

it('toArray() preserves existing data not targeted by operations', function () {
    $result = (new ResourceBuilder('Patient', [
        'resourceType' => 'Patient',
        'id' => 'p1',
        'name' => [['family' => 'Smith']],
    ]))
        ->gender('male')
        ->toArray();

    expect($result['id'])->toBe('p1');
    expect($result['name'])->toBe([['family' => 'Smith']]);
    expect($result['gender'])->toBe('male');
});

it('replace() does not add property if it does not exist in data', function () {
    $result = (new ResourceBuilder('Patient', ['resourceType' => 'Patient']))
        ->nonExistent()->replace('value')
        ->toArray();

    expect($result)->not->toHaveKey('nonExistent');
});
