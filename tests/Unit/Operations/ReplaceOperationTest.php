<?php

declare(strict_types=1);

use FHIR\Flier\Builder\Operations\Operation;
use FHIR\Flier\Builder\Operations\ReplaceOperation;

it('implements Operation interface', function (): void {
    $op = new ReplaceOperation('status', 'active');

    expect($op)->toBeInstanceOf(Operation::class);
});

it('returns the property name', function (): void {
    $op = new ReplaceOperation('status', 'active');

    expect($op->getProperty())->toBe('status');
});

it('returns replace as the operation type', function (): void {
    $op = new ReplaceOperation('status', 'active');

    expect($op->getType())->toBe('replace');
});

it('returns the scalar value', function (): void {
    $op = new ReplaceOperation('status', 'active');

    expect($op->getValue())->toBe('active');
});

it('returns an array value', function (): void {
    $telecom = [['system' => 'phone', 'value' => '555-1234']];
    $op = new ReplaceOperation('telecom', $telecom);

    expect($op->getValue())->toBe($telecom);
});
