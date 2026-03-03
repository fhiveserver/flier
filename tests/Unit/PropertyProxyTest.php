<?php

declare(strict_types=1);

use FHIR\Flier\Builder\Operations\DeleteOperation;
use FHIR\Flier\Builder\Operations\ReplaceOperation;
use FHIR\Flier\Builder\PropertyProxy;
use FHIR\Flier\Builder\ResourceBuilder;

it('returns the current value via value()', function (): void {
    $builder = new ResourceBuilder('Patient', ['gender' => 'male']);
    $proxy = new PropertyProxy('gender', 'male', $builder);

    expect($proxy->value())->toBe('male');
});

it('returns null when property does not exist', function (): void {
    $builder = new ResourceBuilder('Patient', []);
    $proxy = new PropertyProxy('birthDate', null, $builder);

    expect($proxy->value())->toBeNull();
});

it('returns the property name via getPropertyName()', function (): void {
    $builder = new ResourceBuilder('Patient', []);
    $proxy = new PropertyProxy('birthDate', null, $builder);

    expect($proxy->getPropertyName())->toBe('birthDate');
});

it('adds a DeleteOperation when delete() is called', function (): void {
    $builder = new ResourceBuilder('Patient', ['birthDate' => '1990-01-15']);
    $proxy = new PropertyProxy('birthDate', '1990-01-15', $builder);

    $returned = $proxy->delete();

    expect($returned)->toBe($builder);

    $ops = $builder->getOperations();
    expect($ops)->toHaveCount(1)
        ->and($ops[0])->toBeInstanceOf(DeleteOperation::class)
        ->and($ops[0]->getProperty())->toBe('birthDate');
});

it('adds a ReplaceOperation when replace() is called', function (): void {
    $builder = new ResourceBuilder('Patient', ['status' => 'draft']);
    $proxy = new PropertyProxy('status', 'draft', $builder);

    $returned = $proxy->replace('active');

    expect($returned)->toBe($builder);

    $ops = $builder->getOperations();
    expect($ops)->toHaveCount(1)
        ->and($ops[0])->toBeInstanceOf(ReplaceOperation::class)
        ->and($ops[0]->getProperty())->toBe('status')
        ->and($ops[0]->getValue())->toBe('active');
});

it('converts scalar value to string via __toString', function (): void {
    $builder = new ResourceBuilder('Patient', []);
    $proxy = new PropertyProxy('gender', 'male', $builder);

    expect((string) $proxy)->toBe('male');
});

it('converts null value to empty string via __toString', function (): void {
    $builder = new ResourceBuilder('Patient', []);
    $proxy = new PropertyProxy('birthDate', null, $builder);

    expect((string) $proxy)->toBe('');
});

it('converts array value to JSON string via __toString', function (): void {
    $names = [['family' => 'Smith']];
    $builder = new ResourceBuilder('Patient', []);
    $proxy = new PropertyProxy('name', $names, $builder);

    expect((string) $proxy)->toBe(json_encode($names));
});

it('implements Stringable interface', function (): void {
    $builder = new ResourceBuilder('Patient', []);
    $proxy = new PropertyProxy('gender', 'male', $builder);

    expect($proxy)->toBeInstanceOf(Stringable::class);
});
