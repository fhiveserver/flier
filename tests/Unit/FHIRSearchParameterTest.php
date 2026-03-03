<?php

declare(strict_types=1);

use FHIR\Flier\Contracts\FHIRSearchParameter;

it('stores required fields', function (): void {
    $sp = new FHIRSearchParameter(
        code: 'name',
        type: 'string',
        expression: 'Patient.name',
    );

    expect($sp->code)->toBe('name')
        ->and($sp->type)->toBe('string')
        ->and($sp->expression)->toBe('Patient.name');
});

it('defaults description to null', function (): void {
    $sp = new FHIRSearchParameter(code: 'name', type: 'string', expression: 'Patient.name');

    expect($sp->description)->toBeNull();
});

it('stores description when provided', function (): void {
    $sp = new FHIRSearchParameter(
        code: 'name',
        type: 'string',
        expression: 'Patient.name',
        description: 'A server defined search for a patient name',
    );

    expect($sp->description)->toBe('A server defined search for a patient name');
});

it('defaults modifier to empty array', function (): void {
    $sp = new FHIRSearchParameter(code: 'name', type: 'string', expression: 'Patient.name');

    expect($sp->modifier)->toBe([]);
});

it('stores modifiers', function (): void {
    $sp = new FHIRSearchParameter(
        code: 'name',
        type: 'string',
        expression: 'Patient.name',
        modifier: ['exact', 'contains'],
    );

    expect($sp->modifier)->toBe(['exact', 'contains']);
});

it('defaults target to empty array', function (): void {
    $sp = new FHIRSearchParameter(code: 'subject', type: 'reference', expression: 'Observation.subject');

    expect($sp->target)->toBe([]);
});

it('stores target resource types for reference params', function (): void {
    $sp = new FHIRSearchParameter(
        code: 'subject',
        type: 'reference',
        expression: 'Observation.subject',
        target: ['Patient', 'Group'],
    );

    expect($sp->target)->toBe(['Patient', 'Group']);
});

it('defaults component to empty array', function (): void {
    $sp = new FHIRSearchParameter(code: 'combo-code', type: 'composite', expression: 'Observation');

    expect($sp->component)->toBe([]);
});

it('is readonly', function (): void {
    $ref = new ReflectionClass(FHIRSearchParameter::class);

    expect($ref->isReadonly())->toBeTrue();
});
