<?php

declare(strict_types=1);

use FHIVE\Flier\Builder\SearchBuilder;
use FHIVE\Flier\Builder\SearchParam;
use FHIVE\Flier\Drivers\SearchDriver;

// ————————————————————————————————————————————————————————————————
// Constructor & Accessors
// ————————————————————————————————————————————————————————————————

it('stores the resource type', function (): void {
    $builder = new SearchBuilder('Patient');

    expect($builder->getResourceType())->toBe('Patient');
});

it('starts with empty params', function (): void {
    $builder = new SearchBuilder('Patient');

    expect($builder->getParams())->toBe([]);
});

// ————————————————————————————————————————————————————————————————
// Magic __call — adding search parameters
// ————————————————————————————————————————————————————————————————

it('adds a search parameter via magic call', function (): void {
    $builder = new SearchBuilder('Patient');
    $returned = $builder->family('Smith');

    expect($returned)->toBe($builder);

    $params = $builder->getParams();
    expect($params)->toHaveCount(1)
        ->and($params[0])->toBeInstanceOf(SearchParam::class)
        ->and($params[0]->code)->toBe('family')
        ->and($params[0]->rawValue)->toBe('Smith');
});

it('chains multiple search parameters', function (): void {
    $builder = new SearchBuilder('Patient');

    $builder
        ->family('Smith')
        ->birthdate('ge1990-01-01')
        ->gender('male');

    $params = $builder->getParams();
    expect($params)->toHaveCount(3);

    $codes = array_map(fn (SearchParam $p) => $p->code, $params);
    expect($codes)->toBe(['family', 'birthdate', 'gender']);
});

it('defaults type to string when not specified', function (): void {
    $builder = new SearchBuilder('Patient');
    $builder->family('Smith');

    expect($builder->getParams()[0]->type)->toBe('string');
});

it('uses explicit type as second argument', function (): void {
    $builder = new SearchBuilder('Patient');
    $builder->birthdate('ge1990-01-01', 'date');

    expect($builder->getParams()[0]->type)->toBe('date');
});

it('resolves conflict suffix by stripping Property', function (): void {
    $builder = new SearchBuilder('Patient');
    $builder->searchProperty('some-value');

    $params = $builder->getParams();
    expect($params[0]->code)->toBe('search');
});

// ————————————————————————————————————————————————————————————————
// asUrl()
// ————————————————————————————————————————————————————————————————

it('generates a FHIR query string without base URL', function (): void {
    $builder = new SearchBuilder('Patient');
    $builder->family('Smith');

    $url = $builder->asUrl();

    expect($url)->toBe('Patient?family=Smith');
});

it('generates a FHIR query string with base URL', function (): void {
    $builder = new SearchBuilder('Patient');
    $builder->family('Smith');

    $url = $builder->asUrl('https://hapi.fhir.org/baseR4');

    expect($url)->toBe('https://hapi.fhir.org/baseR4/Patient?family=Smith');
});

it('trims trailing slash from base URL', function (): void {
    $builder = new SearchBuilder('Patient');
    $builder->family('Smith');

    $url = $builder->asUrl('https://hapi.fhir.org/baseR4/');

    expect($url)->toBe('https://hapi.fhir.org/baseR4/Patient?family=Smith');
});

it('generates query with multiple parameters', function (): void {
    $builder = new SearchBuilder('Patient');
    $builder
        ->family('Smith')
        ->gender('male')
        ->birthdate('ge1990-01-01');

    $url = $builder->asUrl();

    // http_build_query encodes spaces etc.
    expect($url)->toContain('Patient?')
        ->and($url)->toContain('family=Smith')
        ->and($url)->toContain('gender=male')
        ->and($url)->toContain('birthdate=ge1990-01-01');
});

it('generates empty query string when no params are set', function (): void {
    $builder = new SearchBuilder('Patient');

    $url = $builder->asUrl();

    expect($url)->toBe('Patient?');
});

it('includes modifier in the parameter key', function (): void {
    $builder = new SearchBuilder('Patient');

    // Manually add a param with modifier since magic call does not set modifier
    $ref = new ReflectionProperty($builder, 'params');
    $ref->setValue($builder, [
        new SearchParam(
            code: 'name',
            type: 'string',
            rawValue: 'Smith',
            modifier: 'exact',
        ),
    ]);

    $url = $builder->asUrl();

    expect($url)->toBe('Patient?name%3Aexact=Smith');
});

// ————————————————————————————————————————————————————————————————
// search() — without driver
// ————————————————————————————————————————————————————————————————

it('search without driver returns asUrl result', function (): void {
    $builder = new SearchBuilder('Patient');
    $builder->family('Smith');

    $result = $builder->search();

    expect($result)->toBe($builder->asUrl());
});

// ————————————————————————————————————————————————————————————————
// search() — with driver
// ————————————————————————————————————————————————————————————————

it('search with driver delegates to driver', function (): void {
    $driver = Mockery::mock(SearchDriver::class);
    $driver->shouldReceive('search')
        ->once()
        ->withArgs(function (string $type, array $params): bool {
            return $type === 'Patient' && count($params) === 1;
        })
        ->andReturn(['total' => 5]);

    $builder = new SearchBuilder('Patient');
    $builder->family('Smith');
    $builder->useDriver($driver);

    $result = $builder->search();

    expect($result)->toBe(['total' => 5]);
});

it('useDriver returns the builder for chaining', function (): void {
    $driver = Mockery::mock(SearchDriver::class);
    $builder = new SearchBuilder('Patient');

    $returned = $builder->useDriver($driver);

    expect($returned)->toBe($builder);
});

// ————————————————————————————————————————————————————————————————
// Real-world FHIR search scenarios
// ————————————————————————————————————————————————————————————————

it('builds a Patient search URL with common FHIR params', function (): void {
    $builder = new SearchBuilder('Patient');

    $url = $builder
        ->family('Smith')
        ->given('John')
        ->birthdate('ge1990-01-01')
        ->gender('male')
        ->active('true')
        ->asUrl('https://fhir.example.com/r4');

    expect($url)->toStartWith('https://fhir.example.com/r4/Patient?')
        ->and($url)->toContain('family=Smith')
        ->and($url)->toContain('given=John')
        ->and($url)->toContain('gender=male');
});

it('builds an Observation search URL', function (): void {
    $builder = new SearchBuilder('Observation');

    $url = $builder
        ->code('http://loinc.org|8867-4')
        ->subject('Patient/p1')
        ->date('ge2024-01-01')
        ->asUrl();

    expect($url)->toStartWith('Observation?')
        ->and($url)->toContain('subject=Patient%2Fp1');
});
