<?php

declare(strict_types=1);

use FHIR\Flier\Builder\Operations\AddOperation;
use FHIR\Flier\Builder\Operations\ReplaceOperation;
use FHIR\Flier\Builder\SearchParam;
use FHIR\Flier\Drivers\FHIRHttpDriver;
use Illuminate\Support\Facades\Http;

// — create() ——————————————————————————————————————————————————————————

it('create() POSTs to /{resourceType} with operations applied', function () {
    Http::fake([
        'example.com/Patient' => Http::response(
            ['resourceType' => 'Patient', 'id' => 'new-1', 'gender' => 'male'],
            201,
        ),
    ]);

    $driver = new FHIRHttpDriver('https://example.com');

    $result = $driver->create('Patient', ['resourceType' => 'Patient'], [
        new AddOperation('gender', 'male'),
    ]);

    Http::assertSent(fn ($request) =>
        $request->method() === 'POST' &&
        str_ends_with($request->url(), '/Patient') &&
        $request->data()['gender'] === 'male'
    );

    expect($result['id'])->toBe('new-1');
});

// — update() — FHIR Patch ————————————————————————————————————————————

it('update() PATCHes to /{resourceType}/{id} with FHIRPath Patch payload', function () {
    Http::fake([
        'example.com/Patient/p1' => Http::response(
            ['resourceType' => 'Patient', 'id' => 'p1', 'gender' => 'female'],
            200,
        ),
    ]);

    $driver = new FHIRHttpDriver('https://example.com');

    $result = $driver->update('Patient', ['id' => 'p1'], [
        new ReplaceOperation('gender', 'female'),
    ]);

    Http::assertSent(fn ($request) =>
        $request->method() === 'PATCH' &&
        str_ends_with($request->url(), '/Patient/p1')
    );

    expect($result['gender'])->toBe('female');
});

// — put() —————————————————————————————————————————————————————————————

it('put() PUTs to /{resourceType}/{id} with full payload', function () {
    Http::fake([
        'example.com/Patient/p1' => Http::response(
            ['resourceType' => 'Patient', 'id' => 'p1'],
            200,
        ),
    ]);

    $driver = new FHIRHttpDriver('https://example.com');

    $result = $driver->put('Patient', ['resourceType' => 'Patient', 'id' => 'p1'], []);

    Http::assertSent(fn ($request) =>
        $request->method() === 'PUT' &&
        str_ends_with($request->url(), '/Patient/p1')
    );

    expect($result['id'])->toBe('p1');
});

// — delete() ——————————————————————————————————————————————————————————

it('delete() sends DELETE to /{resourceType}/{id} and returns empty array', function () {
    Http::fake([
        'example.com/Patient/p1' => Http::response(null, 204),
    ]);

    $driver = new FHIRHttpDriver('https://example.com');

    $result = $driver->delete('Patient', ['id' => 'p1']);

    Http::assertSent(fn ($request) =>
        $request->method() === 'DELETE' &&
        str_ends_with($request->url(), '/Patient/p1')
    );

    expect($result)->toBe([]);
});

// — search() ——————————————————————————————————————————————————————————

it('search() sends GET to /{resourceType} with query params', function () {
    Http::fake([
        'example.com/Patient*' => Http::response(
            ['resourceType' => 'Bundle', 'total' => 1, 'entry' => []],
            200,
        ),
    ]);

    $driver = new FHIRHttpDriver('https://example.com');

    $result = $driver->search('Patient', [
        new SearchParam('family', 'string', 'Smith'),
        new SearchParam('gender', 'token', 'male'),
    ]);

    Http::assertSent(fn ($request) =>
        $request->method() === 'GET' &&
        str_contains($request->url(), 'family=Smith') &&
        str_contains($request->url(), 'gender=male')
    );

    expect($result['resourceType'])->toBe('Bundle');
});

// — FHIR headers ——————————————————————————————————————————————————————

it('all requests send FHIR content-type and accept headers', function () {
    Http::fake(['example.com/*' => Http::response(['resourceType' => 'Patient', 'id' => 'x'])]);

    $driver = new FHIRHttpDriver('https://example.com');
    $driver->put('Patient', ['id' => 'x', 'resourceType' => 'Patient'], []);

    Http::assertSent(fn ($request) =>
        $request->header('Accept')[0] === 'application/fhir+json' &&
        $request->header('Content-Type')[0] === 'application/fhir+json'
    );
});
