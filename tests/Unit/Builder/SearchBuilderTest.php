<?php

declare(strict_types=1);

use FHIR\Flier\Builder\SearchBuilder;
use FHIR\Flier\Drivers\SearchDriver;
use FHIR\Flier\Builder\SearchParam;

// — __call — acumula parâmetros ————————————————————————————————————————

it('returns $this for chaining after each param', function () {
    $builder = new SearchBuilder('Patient');

    expect($builder->family('Smith'))->toBe($builder);
});

it('accumulates SearchParams via magic call', function () {
    $builder = new SearchBuilder('Patient');
    $builder->family('Smith')->birthdate('ge1990');

    $params = $builder->getParams();

    expect($params)->toHaveCount(2);
    expect($params[0])->toBeInstanceOf(SearchParam::class);
    expect($params[0]->code)->toBe('family');
    expect($params[0]->rawValue)->toBe('Smith');
    expect($params[1]->code)->toBe('birthdate');
    expect($params[1]->rawValue)->toBe('ge1990');
});

// — Convenção Property — resolução de conflitos ——————————————————————————

it('Property suffix resolves conflict: ->searchProperty() adds param "search"', function () {
    $builder = new SearchBuilder('Patient');
    $builder->searchProperty('value');

    $params = $builder->getParams();
    expect($params[0]->code)->toBe('search');
});

// — search() — executa via driver ou gera URL ————————————————————————————

it('search() without driver returns FHIR query URL', function () {
    $result = (new SearchBuilder('Patient'))
        ->family('Smith')
        ->gender('male')
        ->search();

    expect($result)->toBe('Patient?family=Smith&gender=male');
});

it('search() delegates to driver when set', function () {
    $driver = Mockery::mock(SearchDriver::class);
    $driver->expects('search')->with('Patient', Mockery::any())->andReturn(collect(['p1', 'p2']));

    $result = (new SearchBuilder('Patient'))
        ->useDriver($driver)
        ->family('Smith')
        ->search();

    expect($result->toArray())->toBe(['p1', 'p2']);
});

// — useDriver() fluente ————————————————————————————————————————————————

it('useDriver() returns $this for chaining', function () {
    $driver = Mockery::mock(SearchDriver::class);
    $builder = new SearchBuilder('Patient');

    expect($builder->useDriver($driver))->toBe($builder);
});

it('useDriver() can be chained with params and search', function () {
    $driver = Mockery::mock(SearchDriver::class);
    $driver->expects('search')->andReturn(collect([]));

    (new SearchBuilder('Patient'))
        ->useDriver($driver)
        ->family('Smith')
        ->birthdate('ge1990')
        ->search();
});

// — asUrl() — gera URL ————————————————————————————————————————————————

it('asUrl() generates correct FHIR query string', function () {
    $url = (new SearchBuilder('Patient'))
        ->family('Smith')
        ->gender('male')
        ->asUrl();

    expect($url)->toBe('Patient?family=Smith&gender=male');
});

it('asUrl() prepends base URL when provided', function () {
    $url = (new SearchBuilder('Patient'))
        ->family('Smith')
        ->asUrl('https://hapi.fhir.org/baseR4');

    expect($url)->toBe('https://hapi.fhir.org/baseR4/Patient?family=Smith');
});

it('asUrl() strips trailing slash from base URL', function () {
    $url = (new SearchBuilder('Observation'))
        ->code('29463-7')
        ->asUrl('https://hapi.fhir.org/baseR4/');

    expect($url)->toBe('https://hapi.fhir.org/baseR4/Observation?code=29463-7');
});

it('returns the correct resource type', function () {
    expect((new SearchBuilder('Observation'))->getResourceType())->toBe('Observation');
});
