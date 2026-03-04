<?php

declare(strict_types=1);

use FHIVE\Flier\Builder\Operations\AddOperation;
use FHIVE\Flier\Builder\Operations\DeleteOperation;
use FHIVE\Flier\Builder\Operations\ReplaceOperation;
use FHIVE\Flier\Drivers\ArrayResourceDriver;

beforeEach(function (): void {
    $this->driver = new ArrayResourceDriver;
});

// ————————————————————————————————————————————————————————————————
// apply()
// ————————————————————————————————————————————————————————————————

it('returns data unchanged when no operations are provided', function (): void {
    $data = ['resourceType' => 'Patient', 'gender' => 'male'];

    $result = $this->driver->apply($data, []);

    expect($result)->toBe($data);
});

it('applies an AddOperation to set a new property', function (): void {
    $data = ['resourceType' => 'Patient'];

    $result = $this->driver->apply($data, [
        new AddOperation('gender', 'male'),
    ]);

    expect($result)->toBe([
        'resourceType' => 'Patient',
        'gender' => 'male',
    ]);
});

it('applies an AddOperation that overwrites an existing property', function (): void {
    $data = ['resourceType' => 'Patient', 'gender' => 'male'];

    $result = $this->driver->apply($data, [
        new AddOperation('gender', 'female'),
    ]);

    expect($result['gender'])->toBe('female');
});

it('applies a ReplaceOperation on an existing property', function (): void {
    $data = ['resourceType' => 'Patient', 'status' => 'draft'];

    $result = $this->driver->apply($data, [
        new ReplaceOperation('status', 'active'),
    ]);

    expect($result['status'])->toBe('active');
});

it('ignores a ReplaceOperation on a non-existing property', function (): void {
    $data = ['resourceType' => 'Patient'];

    $result = $this->driver->apply($data, [
        new ReplaceOperation('status', 'active'),
    ]);

    expect($result)->not->toHaveKey('status');
});

it('applies a DeleteOperation to remove a property', function (): void {
    $data = ['resourceType' => 'Patient', 'birthDate' => '1990-01-15'];

    $result = $this->driver->apply($data, [
        new DeleteOperation('birthDate'),
    ]);

    expect($result)->not->toHaveKey('birthDate')
        ->and($result)->toHaveKey('resourceType');
});

it('ignores DeleteOperation on a non-existing property without error', function (): void {
    $data = ['resourceType' => 'Patient'];

    $result = $this->driver->apply($data, [
        new DeleteOperation('nonExistent'),
    ]);

    expect($result)->toBe($data);
});

it('applies multiple operations in sequence', function (): void {
    $data = ['resourceType' => 'Patient', 'gender' => 'male', 'birthDate' => '1990-01-15'];

    $result = $this->driver->apply($data, [
        new AddOperation('active', true),
        new ReplaceOperation('gender', 'female'),
        new DeleteOperation('birthDate'),
    ]);

    expect($result)->toBe([
        'resourceType' => 'Patient',
        'gender' => 'female',
        'active' => true,
    ]);
});

it('handles add then delete of the same property', function (): void {
    $data = ['resourceType' => 'Patient'];

    $result = $this->driver->apply($data, [
        new AddOperation('gender', 'male'),
        new DeleteOperation('gender'),
    ]);

    expect($result)->not->toHaveKey('gender');
});

it('handles delete then add of the same property', function (): void {
    $data = ['resourceType' => 'Patient', 'gender' => 'male'];

    $result = $this->driver->apply($data, [
        new DeleteOperation('gender'),
        new AddOperation('gender', 'other'),
    ]);

    expect($result['gender'])->toBe('other');
});

it('handles replace after add on a previously missing property', function (): void {
    $data = ['resourceType' => 'Patient'];

    $result = $this->driver->apply($data, [
        new AddOperation('status', 'draft'),
        new ReplaceOperation('status', 'active'),
    ]);

    expect($result['status'])->toBe('active');
});

// ————————————————————————————————————————————————————————————————
// create / update / put / delete (ResourceDriver interface)
// ————————————————————————————————————————————————————————————————

it('create applies operations and returns the array', function (): void {
    $data = ['resourceType' => 'Patient'];

    $result = $this->driver->create('Patient', $data, [
        new AddOperation('gender', 'male'),
    ]);

    expect($result)->toBe([
        'resourceType' => 'Patient',
        'gender' => 'male',
    ]);
});

it('update applies operations and returns the array', function (): void {
    $data = ['resourceType' => 'Patient', 'status' => 'draft'];

    $result = $this->driver->update('Patient', $data, [
        new ReplaceOperation('status', 'active'),
    ]);

    expect($result['status'])->toBe('active');
});

it('put applies operations and returns the array', function (): void {
    $data = ['resourceType' => 'Patient'];

    $result = $this->driver->put('Patient', $data, [
        new AddOperation('active', true),
    ]);

    expect($result['active'])->toBeTrue();
});

it('delete returns an empty array', function (): void {
    $data = ['resourceType' => 'Patient', 'id' => fake()->uuid()];

    $result = $this->driver->delete('Patient', $data);

    expect($result)->toBe([]);
});
