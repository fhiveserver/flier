<?php

declare(strict_types=1);

use FHIVE\Flier\Builder\SearchParam;

it('stores code and type and rawValue', function (): void {
    $param = new SearchParam(
        code: 'family',
        type: 'string',
        rawValue: 'Smith',
    );

    expect($param->code)->toBe('family')
        ->and($param->type)->toBe('string')
        ->and($param->rawValue)->toBe('Smith');
});

it('defaults modifier to null', function (): void {
    $param = new SearchParam(code: 'name', type: 'string', rawValue: 'John');

    expect($param->modifier)->toBeNull();
});

it('stores modifier when provided', function (): void {
    $param = new SearchParam(
        code: 'name',
        type: 'string',
        rawValue: 'John',
        modifier: 'exact',
    );

    expect($param->modifier)->toBe('exact');
});

it('defaults prefix to null', function (): void {
    $param = new SearchParam(code: 'birthdate', type: 'date', rawValue: '1990-01-01');

    expect($param->prefix)->toBeNull();
});

it('stores prefix when provided', function (): void {
    $param = new SearchParam(
        code: 'birthdate',
        type: 'date',
        rawValue: 'ge1990-01-01',
        prefix: 'ge',
    );

    expect($param->prefix)->toBe('ge');
});

it('stores token system and code', function (): void {
    $param = new SearchParam(
        code: 'identifier',
        type: 'token',
        rawValue: 'http://example.org|12345',
        tokenSystem: 'http://example.org',
        tokenCode: '12345',
    );

    expect($param->tokenSystem)->toBe('http://example.org')
        ->and($param->tokenCode)->toBe('12345');
});

it('defaults token fields to null', function (): void {
    $param = new SearchParam(code: 'status', type: 'token', rawValue: 'active');

    expect($param->tokenSystem)->toBeNull()
        ->and($param->tokenCode)->toBeNull();
});

it('is readonly', function (): void {
    $ref = new ReflectionClass(SearchParam::class);

    expect($ref->isReadonly())->toBeTrue();
});
