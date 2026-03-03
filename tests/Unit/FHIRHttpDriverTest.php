<?php

declare(strict_types=1);

use FHIR\Flier\Builder\Operations\AddOperation;
use FHIR\Flier\Builder\SearchParam;
use FHIR\Flier\Drivers\FHIRHttpDriver;
use FHIR\Flier\Drivers\ResourceDriver;
use FHIR\Flier\Drivers\SearchDriver;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->driver = new FHIRHttpDriver('https://fhir.example.com/r4');
});

it('implements both ResourceDriver and SearchDriver', function (): void {
    expect($this->driver)->toBeInstanceOf(ResourceDriver::class)
        ->and($this->driver)->toBeInstanceOf(SearchDriver::class);
});

// ————————————————————————————————————————————————————————————————
// create()
// ————————————————————————————————————————————————————————————————

it('sends a POST request on create', function (): void {
    Http::fake([
        'fhir.example.com/r4/Patient' => Http::response([
            'resourceType' => 'Patient',
            'id' => 'new-id',
            'gender' => 'male',
        ], 201),
    ]);

    $result = $this->driver->create('Patient', ['resourceType' => 'Patient'], [
        new AddOperation('gender', 'male'),
    ]);

    expect($result['id'])->toBe('new-id')
        ->and($result['gender'])->toBe('male');

    Http::assertSent(function ($request): bool {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/Patient')
            && $request->header('Accept')[0] === 'application/fhir+json';
    });
});

// ————————————————————————————————————————————————————————————————
// update() — requires id
// ————————————————————————————————————————————————————————————————

it('throws on update without id', function (): void {
    $this->driver->update('Patient', ['resourceType' => 'Patient'], []);
})->throws(InvalidArgumentException::class, "Resource must contain 'id' for PATCH.");

it('sends a PATCH request on update', function (): void {
    Http::fake([
        'fhir.example.com/r4/Patient/*' => Http::response([
            'resourceType' => 'Patient',
            'id' => 'p1',
        ], 200),
    ]);

    $result = $this->driver->update(
        'Patient',
        ['resourceType' => 'Patient', 'id' => 'p1'],
        [new AddOperation('active', true)],
    );

    expect($result['id'])->toBe('p1');

    Http::assertSent(function ($request): bool {
        return $request->method() === 'PATCH'
            && str_contains($request->url(), '/Patient/p1');
    });
});

// ————————————————————————————————————————————————————————————————
// put() — requires id
// ————————————————————————————————————————————————————————————————

it('throws on put without id', function (): void {
    $this->driver->put('Patient', ['resourceType' => 'Patient'], []);
})->throws(InvalidArgumentException::class, "Resource must contain 'id' for PUT.");

it('sends a PUT request on put', function (): void {
    Http::fake([
        'fhir.example.com/r4/Patient/*' => Http::response([
            'resourceType' => 'Patient',
            'id' => 'p1',
        ], 200),
    ]);

    $result = $this->driver->put(
        'Patient',
        ['resourceType' => 'Patient', 'id' => 'p1'],
        [],
    );

    expect($result['id'])->toBe('p1');

    Http::assertSent(function ($request): bool {
        return $request->method() === 'PUT'
            && str_contains($request->url(), '/Patient/p1');
    });
});

// ————————————————————————————————————————————————————————————————
// delete() — requires id
// ————————————————————————————————————————————————————————————————

it('throws on delete without id', function (): void {
    $this->driver->delete('Patient', ['resourceType' => 'Patient']);
})->throws(InvalidArgumentException::class, "Resource must contain 'id' for DELETE.");

it('sends a DELETE request on delete', function (): void {
    Http::fake([
        'fhir.example.com/r4/Patient/*' => Http::response([], 204),
    ]);

    $result = $this->driver->delete('Patient', [
        'resourceType' => 'Patient',
        'id' => 'p1',
    ]);

    expect($result)->toBe([]);

    Http::assertSent(function ($request): bool {
        return $request->method() === 'DELETE'
            && str_contains($request->url(), '/Patient/p1');
    });
});

// ————————————————————————————————————————————————————————————————
// search()
// ————————————————————————————————————————————————————————————————

it('sends a GET request with search parameters', function (): void {
    Http::fake([
        'fhir.example.com/r4/Patient*' => Http::response([
            'resourceType' => 'Bundle',
            'total' => 1,
            'entry' => [],
        ], 200),
    ]);

    $result = $this->driver->search('Patient', [
        new SearchParam(code: 'family', type: 'string', rawValue: 'Smith'),
        new SearchParam(code: 'gender', type: 'token', rawValue: 'male'),
    ]);

    expect($result['resourceType'])->toBe('Bundle');

    Http::assertSent(function ($request): bool {
        return $request->method() === 'GET'
            && str_contains($request->url(), '/Patient')
            && str_contains($request->url(), 'family=Smith')
            && str_contains($request->url(), 'gender=male');
    });
});

it('includes modifier in search query parameter key', function (): void {
    Http::fake([
        'fhir.example.com/r4/Patient*' => Http::response([
            'resourceType' => 'Bundle',
            'total' => 0,
        ], 200),
    ]);

    $this->driver->search('Patient', [
        new SearchParam(
            code: 'name',
            type: 'string',
            rawValue: 'Smith',
            modifier: 'exact',
        ),
    ]);

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'name%3Aexact=Smith')
            || str_contains($request->url(), 'name:exact=Smith');
    });
});
