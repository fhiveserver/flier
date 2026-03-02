<?php

declare(strict_types=1);

use FHIR\Flier\Builder\Operations\AddOperation;
use FHIR\Flier\Builder\Operations\DeleteOperation;
use FHIR\Flier\Builder\Operations\ReplaceOperation;
use FHIR\Flier\Drivers\ArrayResourceDriver;

it('create() applies add operations', function () {
    $driver = new ArrayResourceDriver;

    $result = $driver->create('Patient', ['resourceType' => 'Patient'], [
        new AddOperation('gender', 'male'),
    ]);

    expect($result['gender'])->toBe('male');
});

it('update() applies replace operations', function () {
    $driver = new ArrayResourceDriver;

    $result = $driver->update('Patient', ['resourceType' => 'Patient', 'gender' => 'male'], [
        new ReplaceOperation('gender', 'female'),
    ]);

    expect($result['gender'])->toBe('female');
});

it('put() applies operations and returns full array', function () {
    $driver = new ArrayResourceDriver;

    $result = $driver->put('Patient', ['resourceType' => 'Patient'], [
        new AddOperation('id', 'p1'),
    ]);

    expect($result['id'])->toBe('p1');
});

it('delete() always returns empty array', function () {
    $driver = new ArrayResourceDriver;

    $result = $driver->delete('Patient', ['resourceType' => 'Patient', 'id' => 'p1']);

    expect($result)->toBe([]);
});

it('apply() handles all three operation types', function () {
    $driver = new ArrayResourceDriver;

    $result = $driver->apply(
        ['resourceType' => 'Patient', 'gender' => 'male', 'status' => 'active'],
        [
            new AddOperation('id', 'p1'),
            new ReplaceOperation('gender', 'female'),
            new DeleteOperation('status'),
        ],
    );

    expect($result['id'])->toBe('p1');
    expect($result['gender'])->toBe('female');
    expect($result)->not->toHaveKey('status');
});

it('replace() does not add property if absent', function () {
    $driver = new ArrayResourceDriver;

    $result = $driver->apply(['resourceType' => 'Patient'], [
        new ReplaceOperation('nonExistent', 'value'),
    ]);

    expect($result)->not->toHaveKey('nonExistent');
});
