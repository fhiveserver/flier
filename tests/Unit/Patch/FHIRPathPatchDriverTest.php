<?php

declare(strict_types=1);

use FHIR\Flier\Flier;
use FHIR\Flier\Patch\FHIRPathPatchDriver;

// Helpers locais do arquivo de teste.
// Local helpers for this test file.
function makeDriver(): FHIRPathPatchDriver
{
    return new FHIRPathPatchDriver;
}

// — Estrutura básica do Parameters resource ——————————————————————————————

it('wraps operations in a Parameters resource', function () {
    $patch = makeDriver()->generate('Patient', []);

    expect($patch['resourceType'])->toBe('Parameters');
    expect($patch['parameter'])->toBe([]);
});

// — type="add" ——————————————————————————————————————————————————————————

it('generates add operation for a string value', function () {
    $patch = Flier::resource('Patient')
        ->gender('male')
        ->asFHIRPatch();

    $part = $patch['parameter'][0]['part'];

    expect($part)->toContain(['name' => 'type', 'valueCode' => 'add']);
    expect($part)->toContain(['name' => 'path', 'valueString' => 'Patient']);
    expect($part)->toContain(['name' => 'name', 'valueString' => 'gender']);
    expect($part)->toContain(['name' => 'value', 'valueString' => 'male']);
});

it('generates add operation for a date value', function () {
    $patch = Flier::resource('Patient')
        ->birthDate('1990-01-15')
        ->asFHIRPatch();

    $part = $patch['parameter'][0]['part'];

    expect($part)->toContain(['name' => 'value', 'valueDate' => '1990-01-15']);
});

it('generates add operation for a dateTime value', function () {
    $patch = Flier::resource('Patient')
        ->deceasedDateTime('2024-03-01T10:30:00Z')
        ->asFHIRPatch();

    $part = $patch['parameter'][0]['part'];

    expect($part)->toContain(['name' => 'value', 'valueDateTime' => '2024-03-01T10:30:00Z']);
});

it('generates add operation for a boolean value', function () {
    $patch = Flier::resource('Patient')
        ->deceasedBoolean(true)
        ->asFHIRPatch();

    $part = $patch['parameter'][0]['part'];

    expect($part)->toContain(['name' => 'value', 'valueBoolean' => true]);
});

it('generates add operation for an integer value', function () {
    $patch = Flier::resource('Patient')
        ->multipleBirthInteger(2)
        ->asFHIRPatch();

    $part = $patch['parameter'][0]['part'];

    expect($part)->toContain(['name' => 'value', 'valueInteger' => 2]);
});

it('generates add operation for an array value (serialized as JSON string)', function () {
    $names = [['family' => 'Doe', 'given' => ['John']]];

    $patch = Flier::resource('Patient')
        ->name($names)
        ->asFHIRPatch();

    $part = $patch['parameter'][0]['part'];

    expect($part[3]['name'])->toBe('value');
    expect($part[3])->toHaveKey('valueString');
    expect(json_decode($part[3]['valueString'], true))->toBe($names);
});

// — type="delete" ————————————————————————————————————————————————————————

it('generates delete operation', function () {
    $patch = Flier::resource('Patient', ['birthDate' => '1990-01-15'])
        ->birthDate()->delete()
        ->asFHIRPatch();

    $part = $patch['parameter'][0]['part'];

    expect($part)->toContain(['name' => 'type', 'valueCode' => 'delete']);
    expect($part)->toContain(['name' => 'path', 'valueString' => 'Patient.birthDate']);
    expect(count($part))->toBe(2); // sem value
});

// — type="replace" ————————————————————————————————————————————————————————

it('generates replace operation', function () {
    $patch = Flier::resource('Patient', ['gender' => 'male'])
        ->gender()->replace('female')
        ->asFHIRPatch();

    $part = $patch['parameter'][0]['part'];

    expect($part)->toContain(['name' => 'type', 'valueCode' => 'replace']);
    expect($part)->toContain(['name' => 'path', 'valueString' => 'Patient.gender']);
    expect($part)->toContain(['name' => 'value', 'valueString' => 'female']);
});

// — Múltiplas operações / Multiple operations —————————————————————————————

it('generates multiple operations in order', function () {
    $patch = Flier::resource('Patient', ['birthDate' => '1990-01-15', 'gender' => 'male'])
        ->birthDate()->delete()
        ->status()->replace('inactive')
        ->name([['family' => 'Doe']])
        ->asFHIRPatch();

    expect($patch['parameter'])->toHaveCount(3);
    expect($patch['parameter'][0]['part'][0])->toBe(['name' => 'type', 'valueCode' => 'delete']);
    expect($patch['parameter'][1]['part'][0])->toBe(['name' => 'type', 'valueCode' => 'replace']);
    expect($patch['parameter'][2]['part'][0])->toBe(['name' => 'type', 'valueCode' => 'add']);
});
