<?php

declare(strict_types=1);

use FHIR\Flier\Builder\Operations\AddOperation;
use FHIR\Flier\Builder\Operations\Operation;

it('implements Operation interface', function (): void {
    $op = new AddOperation('gender', 'male');

    expect($op)->toBeInstanceOf(Operation::class);
});

it('returns the property name', function (): void {
    $op = new AddOperation('birthDate', '1990-01-15');

    expect($op->getProperty())->toBe('birthDate');
});

it('returns add as the operation type', function (): void {
    $op = new AddOperation('name', []);

    expect($op->getType())->toBe('add');
});

it('returns the scalar value', function (): void {
    $op = new AddOperation('gender', 'male');

    expect($op->getValue())->toBe('male');
});

it('returns an array value', function (): void {
    $names = [['family' => 'Smith', 'given' => ['John']]];
    $op = new AddOperation('name', $names);

    expect($op->getValue())->toBe($names);
});

it('returns a boolean value', function (): void {
    $op = new AddOperation('active', true);

    expect($op->getValue())->toBeTrue();
});

it('returns null value', function (): void {
    $op = new AddOperation('extension', null);

    expect($op->getValue())->toBeNull();
});
