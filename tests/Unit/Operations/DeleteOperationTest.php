<?php

declare(strict_types=1);

use FHIVE\Flier\Builder\Operations\DeleteOperation;
use FHIVE\Flier\Builder\Operations\Operation;

it('implements Operation interface', function (): void {
    $op = new DeleteOperation('birthDate');

    expect($op)->toBeInstanceOf(Operation::class);
});

it('returns the property name', function (): void {
    $op = new DeleteOperation('birthDate');

    expect($op->getProperty())->toBe('birthDate');
});

it('returns delete as the operation type', function (): void {
    $op = new DeleteOperation('gender');

    expect($op->getType())->toBe('delete');
});
