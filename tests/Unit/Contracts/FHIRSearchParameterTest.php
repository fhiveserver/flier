<?php

declare(strict_types=1);

use FHIR\Flier\Contracts\FHIRSearchParameter;

it('creates immutable search parameter', function () {
    $param = new FHIRSearchParameter(
        code: 'name',
        type: 'string',
        expression: 'Patient.name',
    );

    expect($param->code)->toBe('name');
    expect($param->type)->toBe('string');
    expect($param->expression)->toBe('Patient.name');
    expect($param->modifier)->toBe([]);
    expect($param->target)->toBe([]);
});

it('allows modifiers and targets', function () {
    $param = new FHIRSearchParameter(
        code: 'subject',
        type: 'reference',
        expression: 'Condition.subject',
        modifier: ['identifier'],
        target: ['Patient', 'Group'],
    );

    expect($param->modifier)->toBe(['identifier']);
    expect($param->target)->toBe(['Patient', 'Group']);
});

it('has optional description', function () {
    $param = new FHIRSearchParameter(
        code: 'family',
        type: 'string',
        expression: 'Patient.name.family',
        description: 'A portion of the family name of the patient',
    );

    expect($param->description)->toBe('A portion of the family name of the patient');
});
