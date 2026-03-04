<?php

declare(strict_types=1);

use FHIVE\Flier\Builder\Operations\AddOperation;
use FHIVE\Flier\Builder\Operations\DeleteOperation;
use FHIVE\Flier\Builder\Operations\ReplaceOperation;
use FHIVE\Flier\Patch\FHIRPathPatchDriver;

beforeEach(function (): void {
    $this->driver = new FHIRPathPatchDriver;
});

it('generates a Parameters resource with resourceType', function (): void {
    $result = $this->driver->generate('Patient', []);

    expect($result['resourceType'])->toBe('Parameters')
        ->and($result)->toHaveKey('parameter');
});

it('generates empty parameter array when no operations provided', function (): void {
    $result = $this->driver->generate('Patient', []);

    expect($result['parameter'])->toBe([]);
});

// ————————————————————————————————————————————————————————————————
// Add operations — per FHIRPath Patch spec
// ————————————————————————————————————————————————————————————————

it('generates add operation with path pointing to parent resource', function (): void {
    $result = $this->driver->generate('Patient', [
        new AddOperation('gender', 'male'),
    ]);

    $parts = $result['parameter'][0]['part'];

    // type = "add"
    expect($parts[0])->toBe(['name' => 'type', 'valueCode' => 'add']);

    // path = "Patient" (parent, not Patient.gender)
    expect($parts[1])->toBe(['name' => 'path', 'valueString' => 'Patient']);

    // name = "gender" (property name)
    expect($parts[2])->toBe(['name' => 'name', 'valueString' => 'gender']);
});

it('generates add operation with valueString for string values', function (): void {
    $result = $this->driver->generate('Patient', [
        new AddOperation('gender', 'male'),
    ]);

    $parts = $result['parameter'][0]['part'];
    $valuePart = $parts[3];

    expect($valuePart['name'])->toBe('value')
        ->and($valuePart)->toHaveKey('valueString')
        ->and($valuePart['valueString'])->toBe('male');
});

it('generates add operation with valueBoolean for boolean values', function (): void {
    $result = $this->driver->generate('Patient', [
        new AddOperation('active', true),
    ]);

    $parts = $result['parameter'][0]['part'];
    $valuePart = $parts[3];

    expect($valuePart['name'])->toBe('value')
        ->and($valuePart)->toHaveKey('valueBoolean')
        ->and($valuePart['valueBoolean'])->toBeTrue();
});

it('generates add operation with valueInteger for integer values', function (): void {
    $result = $this->driver->generate('Patient', [
        new AddOperation('multipleBirthInteger', 2),
    ]);

    $parts = $result['parameter'][0]['part'];
    $valuePart = $parts[3];

    expect($valuePart['name'])->toBe('value')
        ->and($valuePart)->toHaveKey('valueInteger')
        ->and($valuePart['valueInteger'])->toBe(2);
});

it('generates add operation with valueDecimal for float values', function (): void {
    $result = $this->driver->generate('Observation', [
        new AddOperation('valueQuantity', 98.6),
    ]);

    $parts = $result['parameter'][0]['part'];
    $valuePart = $parts[3];

    expect($valuePart['name'])->toBe('value')
        ->and($valuePart)->toHaveKey('valueDecimal')
        ->and($valuePart['valueDecimal'])->toBe(98.6);
});

it('generates add operation with valueDate for FHIR date strings', function (): void {
    $result = $this->driver->generate('Patient', [
        new AddOperation('birthDate', '1990-01-15'),
    ]);

    $parts = $result['parameter'][0]['part'];
    $valuePart = $parts[3];

    expect($valuePart['name'])->toBe('value')
        ->and($valuePart)->toHaveKey('valueDate')
        ->and($valuePart['valueDate'])->toBe('1990-01-15');
});

it('generates add operation with valueDateTime for FHIR dateTime strings', function (): void {
    $result = $this->driver->generate('Observation', [
        new AddOperation('effectiveDateTime', '2024-01-15T10:30:00Z'),
    ]);

    $parts = $result['parameter'][0]['part'];
    $valuePart = $parts[3];

    expect($valuePart['name'])->toBe('value')
        ->and($valuePart)->toHaveKey('valueDateTime')
        ->and($valuePart['valueDateTime'])->toBe('2024-01-15T10:30:00Z');
});

it('serializes array values as JSON valueString', function (): void {
    $names = [['family' => 'Smith', 'given' => ['John']]];

    $result = $this->driver->generate('Patient', [
        new AddOperation('name', $names),
    ]);

    $parts = $result['parameter'][0]['part'];
    $valuePart = $parts[3];

    expect($valuePart['name'])->toBe('value')
        ->and($valuePart)->toHaveKey('valueString')
        ->and($valuePart['valueString'])->toBe(json_encode($names));
});

it('omits value parts when AddOperation value is null', function (): void {
    $result = $this->driver->generate('Patient', [
        new AddOperation('extension', null),
    ]);

    $parts = $result['parameter'][0]['part'];

    // Should have type, path, name — but no value part
    expect($parts)->toHaveCount(3);
});

// ————————————————————————————————————————————————————————————————
// Replace operations — per FHIRPath Patch spec
// ————————————————————————————————————————————————————————————————

it('generates replace operation with path pointing to the property', function (): void {
    $result = $this->driver->generate('Patient', [
        new ReplaceOperation('status', 'active'),
    ]);

    $parts = $result['parameter'][0]['part'];

    // type = "replace"
    expect($parts[0])->toBe(['name' => 'type', 'valueCode' => 'replace']);

    // path = "Patient.status" (direct path to property, unlike add)
    expect($parts[1])->toBe(['name' => 'path', 'valueString' => 'Patient.status']);

    // No "name" part for replace (only add has it)
});

it('generates replace operation with correct value', function (): void {
    $result = $this->driver->generate('Patient', [
        new ReplaceOperation('active', false),
    ]);

    $parts = $result['parameter'][0]['part'];
    $valuePart = $parts[2];

    expect($valuePart['name'])->toBe('value')
        ->and($valuePart)->toHaveKey('valueBoolean')
        ->and($valuePart['valueBoolean'])->toBeFalse();
});

// ————————————————————————————————————————————————————————————————
// Delete operations — per FHIRPath Patch spec
// ————————————————————————————————————————————————————————————————

it('generates delete operation with path and no value', function (): void {
    $result = $this->driver->generate('Patient', [
        new DeleteOperation('birthDate'),
    ]);

    $parts = $result['parameter'][0]['part'];

    expect($parts)->toHaveCount(2)
        ->and($parts[0])->toBe(['name' => 'type', 'valueCode' => 'delete'])
        ->and($parts[1])->toBe(['name' => 'path', 'valueString' => 'Patient.birthDate']);
});

// ————————————————————————————————————————————————————————————————
// Multiple operations
// ————————————————————————————————————————————————————————————————

it('generates multiple operations in order', function (): void {
    $result = $this->driver->generate('Patient', [
        new DeleteOperation('birthDate'),
        new AddOperation('gender', 'male'),
        new ReplaceOperation('active', true),
    ]);

    expect($result['parameter'])->toHaveCount(3);

    // First operation: delete
    $types = array_map(
        fn (array $p) => $p['part'][0]['valueCode'],
        $result['parameter'],
    );

    expect($types)->toBe(['delete', 'add', 'replace']);
});

// ————————————————————————————————————————————————————————————————
// Date detection edge cases
// ————————————————————————————————————————————————————————————————

it('detects YYYY-MM-DD as valueDate', function (): void {
    $result = $this->driver->generate('Patient', [
        new AddOperation('birthDate', '2024-12-31'),
    ]);

    $parts = $result['parameter'][0]['part'];

    expect($parts[3])->toHaveKey('valueDate');
});

it('detects YYYY-MM-DDTHH:MM as valueDateTime', function (): void {
    $result = $this->driver->generate('Observation', [
        new AddOperation('issued', '2024-01-15T14:30:00+01:00'),
    ]);

    $parts = $result['parameter'][0]['part'];

    expect($parts[3])->toHaveKey('valueDateTime');
});

it('does not detect partial date patterns as valueDate', function (): void {
    $result = $this->driver->generate('Patient', [
        new AddOperation('note', '2024-01'),
    ]);

    $parts = $result['parameter'][0]['part'];

    // Partial dates should be plain valueString
    expect($parts[3])->toHaveKey('valueString');
});

it('does not detect non-date strings as valueDate', function (): void {
    $result = $this->driver->generate('Patient', [
        new AddOperation('gender', 'male'),
    ]);

    $parts = $result['parameter'][0]['part'];

    expect($parts[3])->toHaveKey('valueString')
        ->and($parts[3])->not->toHaveKey('valueDate');
});
