# Flier — Complete Guide

This guide covers every feature of Flier. If you're looking for a quick overview, start with the [README](../README.md).

---

## Table of contents

- [Installation and setup](#installation-and-setup)
- [ResourceBuilder](#resourcebuilder)
  - [Creating a resource](#creating-a-resource)
  - [Editing an existing resource](#editing-an-existing-resource)
  - [Using a driver](#using-a-driver)
  - [Reserved method names](#reserved-method-names)
  - [All ResourceBuilder methods](#all-resourcebuilder-methods)
- [PropertyProxy](#propertyproxy)
- [Operations](#operations)
- [FHIRPath Patch](#fhirpath-patch)
- [SearchBuilder](#searchbuilder)
  - [Building a URL](#building-a-url)
  - [Executing a search](#executing-a-search)
  - [Modifiers](#modifiers)
  - [All SearchBuilder methods](#all-searchbuilder-methods)
- [Drivers](#drivers)
  - [ArrayResourceDriver](#arrayresourcedriver)
  - [FHIRHttpDriver](#fhirhttpdriver)
  - [Writing your own driver](#writing-your-own-driver)
- [Parsing FHIR responses](#parsing-fhir-responses)
- [Macros](#macros)
- [Search parameter sources](#search-parameter-sources)
  - [InMemorySearchParameterSource](#inmemorysearchparametersource)
  - [CompositeSearchParameterSource](#compositesearchparametersource)
  - [Writing your own source](#writing-your-own-source)

---

## Installation and setup

```bash
composer require fhive/flier
```

Laravel auto-discovers `FlierServiceProvider`. No manual registration, no config file to publish — it just works.

---

## ResourceBuilder

`ResourceBuilder` is the core of Flier. It lets you build and edit FHIR resources using a fluent PHP API. Every FHIR property name becomes a method via `__call`. Operations are recorded as an immutable log and applied only when you ask for a result.

### Creating a resource

Start with `Flier::resource($type)` and chain property setters:

```php
use FHIR\Flier\Flier;

$patient = Flier::resource('Patient')
    ->name([['family' => 'Costa', 'given' => ['Mariana']]])
    ->birthDate('1992-08-10')
    ->gender('female')
    ->active(true)
    ->toArray();
```

`toArray()` applies all accumulated operations and returns the final array. Nothing is sent anywhere.

You can use any valid FHIR property name — Flier doesn't know or care what they are. `birthDate`, `status`, `valueQuantity`, `component` — all work the same way.

### Editing an existing resource

Pass an existing resource array as the second argument:

```php
$existing = [
    'resourceType' => 'Patient',
    'id' => 'p1',
    'gender' => 'male',
    'birthDate' => '1990-01-01',
];

$updated = Flier::resource('Patient', $existing)
    ->birthDate('1990-03-15')  // replaces via AddOperation
    ->active(true)             // adds new property
    ->toArray();
```

> **Heads up:** calling `->birthDate('1990-03-15')` on a resource that already has `birthDate` records an `AddOperation`, which **replaces** the existing value. If you want to be explicit about the intent, use `->birthDate()->replace('1990-03-15')` — it generates a `replace` operation in the FHIRPath Patch document.

### Using a driver

By default, all operations stay in memory. To send them somewhere, set a driver with `useDriver()`:

```php
$driver = new FHIRHttpDriver('https://hapi.fhir.org/baseR4');

Flier::resource('Patient')
    ->name([['family' => 'Doe']])
    ->gender('female')
    ->useDriver($driver)
    ->create(); // POST /Patient
```

The driver can also be passed as a class string and will be resolved from the container:

```php
->useDriver(MyCustomDriver::class)
```

### Reserved method names

The following method names are reserved — they're real methods on `ResourceBuilder` and won't be intercepted as FHIR property setters:

```
create   update   put   delete   index
useDriver   toArray   asFHIRPatch   addOperation
getData   getOperations   getResourceType
```

If a FHIR resource happens to have a property with one of these names (unlikely, but possible with custom resources), append `Property` to the method name:

```php
// Sets the FHIR property "delete" on a custom resource
$builder->deleteProperty('some-value');

// Sets the FHIR property "create"
$builder->createProperty('some-mode');
```

The same convention applies to `SearchBuilder` — `->searchProperty('value')` sets the FHIR search parameter `search`.

### All ResourceBuilder methods

| Method | Returns | Description |
|---|---|---|
| `->someProperty($value)` | `static` | Records an `AddOperation` for `someProperty` |
| `->someProperty()` | `PropertyProxy` | Returns a proxy for surgical edits (see below) |
| `->useDriver($driver)` | `static` | Sets the operation driver |
| `->create()` | `mixed` | POST semantics — applies ops or delegates to driver |
| `->update()` | `mixed` | PATCH semantics — applies ops or sends FHIRPath Patch |
| `->put()` | `mixed` | PUT semantics — full replace or delegates to driver |
| `->delete()` | `mixed` | DELETE semantics — empty array or delegates to driver |
| `->toArray()` | `array` | Applies all operations, returns the result |
| `->asFHIRPatch()` | `array` | Generates a `Parameters` resource for FHIRPath Patch |
| `->addOperation($op)` | `static` | Adds a pre-built operation (used by `PropertyProxy`) |
| `->getData()` | `array` | Returns the current raw data (before operations) |
| `->getOperations()` | `list<Operation>` | Returns the list of pending operations |
| `->getResourceType()` | `string` | Returns the resource type (e.g. `"Patient"`) |

---

## PropertyProxy

Calling a property method **without arguments** returns a `PropertyProxy` — a small immutable cursor that gives you control over what operation gets recorded.

```php
$builder = Flier::resource('Patient', $existing);

// Delete a property
$builder->deceased()->delete();

// Replace a property's value
$builder->status()->replace('active');

// Read the current value without recording any operation
$currentName = $builder->name()->value();

// Cast to string
echo $builder->birthDate(); // calls __toString() on the proxy
```

`delete()` and `replace()` both return the parent `ResourceBuilder`, so you can keep chaining:

```php
Flier::resource('Patient', $existing)
    ->deceased()->delete()
    ->status()->replace('active')
    ->gender()->replace('female')
    ->toArray();
```

---

## Operations

Flier records mutations as operation objects. They're applied in order when you call `toArray()`, `create()`, `update()`, or `put()`.

| Operation | Created by | Applies as | FHIRPath Patch type |
|---|---|---|---|
| `AddOperation` | `->prop($value)` | Sets `data['prop'] = $value` | `add` |
| `ReplaceOperation` | `->prop()->replace($value)` | Sets `data['prop'] = $value` | `replace` |
| `DeleteOperation` | `->prop()->delete()` | Unsets `data['prop']` | `delete` |

You can also add operations directly if you're building tooling on top of Flier:

```php
use FHIR\Flier\Builder\Operations\AddOperation;
use FHIR\Flier\Builder\Operations\DeleteOperation;
use FHIR\Flier\Builder\Operations\ReplaceOperation;

$builder->addOperation(new AddOperation('status', 'active'));
$builder->addOperation(new ReplaceOperation('gender', 'female'));
$builder->addOperation(new DeleteOperation('deceased'));
```

To inspect what's pending without applying anything:

```php
$ops = $builder->getOperations(); // list<Operation>
```

---

## FHIRPath Patch

When you call `update()` with an HTTP driver, Flier generates a [FHIRPath Patch](https://hl7.org/fhir/http.html#patch) document from the operation log and sends it as a `PATCH` request. You can also generate the document yourself:

```php
$patch = Flier::resource('Patient', $existing)
    ->status()->replace('inactive')
    ->birthDate()->delete()
    ->name([['family' => 'Novo']])
    ->asFHIRPatch();
```

The result is a `Parameters` FHIR resource:

```php
[
    'resourceType' => 'Parameters',
    'parameter' => [
        [
            'name' => 'operation',
            'part' => [
                ['name' => 'type',  'valueCode'   => 'replace'],
                ['name' => 'path',  'valueString' => 'Patient.status'],
                ['name' => 'value', 'valueString' => 'inactive'],
            ],
        ],
        [
            'name' => 'operation',
            'part' => [
                ['name' => 'type', 'valueCode'   => 'delete'],
                ['name' => 'path', 'valueString' => 'Patient.birthDate'],
            ],
        ],
        [
            'name' => 'operation',
            'part' => [
                ['name' => 'type',  'valueCode'   => 'add'],
                ['name' => 'path',  'valueString' => 'Patient'],
                ['name' => 'name',  'valueString' => 'name'],
                ['name' => 'value', 'valueArray'  => [['family' => 'Novo']]],
            ],
        ],
    ],
]
```

This is exactly what `FHIRHttpDriver::update()` sends over the wire.

---

## SearchBuilder

`SearchBuilder` accumulates FHIR search parameters and either builds a URL or delegates to a search driver. Like `ResourceBuilder`, every parameter name is a magic method.

### Building a URL

```php
$url = Flier::search('Patient')
    ->family('Silva')
    ->birthdate('ge1990-01-01')
    ->gender('male')
    ->asUrl('https://hapi.fhir.org/baseR4');

// → "https://hapi.fhir.org/baseR4/Patient?family=Silva&birthdate=ge1990-01-01&gender=male"
```

Without a base URL, `asUrl()` returns just the path:

```php
Flier::search('Observation')->code('8867-4')->asUrl();
// → "Observation?code=8867-4"
```

### Executing a search

With `useDriver()`, `search()` delegates to the driver and returns whatever it gives back:

```php
// Against an external server — returns the decoded Bundle array
$bundle = Flier::search('Patient')
    ->family('Silva')
    ->useDriver(new FHIRHttpDriver('https://hapi.fhir.org/baseR4'))
    ->search();

// Against a local index (fhive/flier-storage) — returns Collection<int, Resource>
$resources = Flier::search('Patient')
    ->family('Silva')
    ->useDriver(app(\FHIR\Flier\Drivers\SearchDriver::class))
    ->search();
```

Without a driver, `search()` returns the same as `asUrl()` — just the query string.

### Modifiers

FHIR search supports modifiers like `:exact`, `:contains`, `:text`, `:not`. Pass them as a second argument:

```php
Flier::search('Patient')
    ->name('Silva', ':exact')       // name:exact=Silva
    ->identifier('12345', ':text')  // identifier:text=12345
    ->gender('male', ':not')        // gender:not=male
```

### All SearchBuilder methods

| Method | Returns | Description |
|---|---|---|
| `->someParam($value, $modifier?)` | `static` | Adds a search parameter |
| `->useDriver($driver)` | `static` | Sets the search driver |
| `->search()` | `mixed` | Executes — URL string without driver, driver result with driver |
| `->asUrl($baseUrl?)` | `string` | Generates the query string with optional base URL |
| `->getParams()` | `list<SearchParam>` | Returns the accumulated parameters |
| `->getResourceType()` | `string` | Returns the resource type |

---

## Drivers

Drivers are the mechanism through which Flier communicates with the outside world. A `ResourceDriver` handles create, update, put, and delete. A `SearchDriver` handles search. `FHIRHttpDriver` implements both.

### ArrayResourceDriver

The default driver. Applies operations in memory and returns the resulting array. No network, no database, no dependencies.

```php
use FHIR\Flier\Drivers\ArrayResourceDriver;

$driver = new ArrayResourceDriver;

// Low-level: apply operations directly
$result = $driver->apply($data, $operations);
```

You don't usually need to instantiate this yourself — it's what `toArray()` uses internally.

### FHIRHttpDriver

Sends operations to an external FHIR server using Laravel's `Http` facade. Handles all FHIR HTTP semantics correctly:

- `create()` → `POST /{resourceType}` with the full resource body
- `update()` → `PATCH /{resourceType}/{id}` with a FHIRPath Patch Parameters resource
- `put()` → `PUT /{resourceType}/{id}` with the full resource body
- `delete()` → `DELETE /{resourceType}/{id}`
- `search()` → `GET /{resourceType}?...` with query parameters

All requests use `application/fhir+json` for both `Accept` and `Content-Type`.

```php
use FHIR\Flier\Drivers\FHIRHttpDriver;

$driver = new FHIRHttpDriver('https://hapi.fhir.org/baseR4');

// Create
Flier::resource('Patient')
    ->name([['family' => 'Doe']])
    ->gender('female')
    ->useDriver($driver)
    ->create();

// Update with FHIRPath Patch
Flier::resource('Patient', ['id' => 'p1', ...])
    ->status()->replace('inactive')
    ->useDriver($driver)
    ->update();

// PUT — full replace
Flier::resource('Patient', $fullResource)
    ->useDriver($driver)
    ->put();

// DELETE
Flier::resource('Patient', ['id' => 'p1'])
    ->useDriver($driver)
    ->delete();

// Search — returns the decoded Bundle array
$bundle = Flier::search('Patient')
    ->family('Doe')
    ->useDriver($driver)
    ->search();
```

### Writing your own driver

Implement `ResourceDriver`, `SearchDriver`, or both:

```php
use FHIR\Flier\Builder\Operations\Operation;
use FHIR\Flier\Builder\SearchParam;
use FHIR\Flier\Drivers\ResourceDriver;
use FHIR\Flier\Drivers\SearchDriver;

class AuditingFHIRDriver implements ResourceDriver, SearchDriver
{
    public function __construct(
        private readonly ResourceDriver $inner,
        private readonly AuditService $audit,
    ) {}

    public function create(string $resourceType, array $data, array $operations): array
    {
        $result = $this->inner->create($resourceType, $data, $operations);
        $this->audit->record('create', $resourceType, $result['id'] ?? null);

        return $result;
    }

    public function update(string $resourceType, array $data, array $operations): array
    {
        $result = $this->inner->update($resourceType, $data, $operations);
        $this->audit->record('update', $resourceType, $data['id'] ?? null);

        return $result;
    }

    public function put(string $resourceType, array $data, array $operations): array
    {
        return $this->inner->put($resourceType, $data, $operations);
    }

    public function delete(string $resourceType, array $data): array
    {
        $this->audit->record('delete', $resourceType, $data['id'] ?? null);

        return $this->inner->delete($resourceType, $data);
    }

    /** @param list<SearchParam> $params */
    public function search(string $resourceType, array $params): mixed
    {
        return $this->inner->search($resourceType, $params);
    }
}
```

Then use it:

```php
$driver = new AuditingFHIRDriver(
    new FHIRHttpDriver('https://hapi.fhir.org/baseR4'),
    app(AuditService::class),
);

Flier::resource('Patient', $data)
    ->status()->replace('inactive')
    ->useDriver($driver)
    ->update();
```

---

## Parsing FHIR responses

When you receive a response from a FHIR server, you can wrap it in a builder for further editing:

```php
// Single resource
$response = Http::get('https://hapi.fhir.org/baseR4/Patient/p1')->json();

$builder = Flier::from($response);
echo $builder->getResourceType(); // "Patient"
echo $builder->name()->value();   // the name array
```

`Flier::from()` throws `InvalidArgumentException` if the array doesn't have a `resourceType` key.

### Parsing a Bundle

```php
$bundle = Http::get('https://hapi.fhir.org/baseR4/Patient?family=Silva')->json();

// Returns a Collection<int, ResourceBuilder>
$builders = Flier::fromBundle($bundle);

// Work with specific types
$patients = $builders->filter(fn ($b) => $b->getResourceType() === 'Patient');

// Extract data
$names = $patients->map(fn ($b) => $b->name()->value());

// Edit and batch — each builder is independent
$patches = $patients->map(fn ($b) =>
    $b->status()->replace('active')->asFHIRPatch()
);
```

Entries in the Bundle without a `resource` key are silently skipped.

---

## Macros

Both `ResourceBuilder` and `SearchBuilder` use `Illuminate\Support\Traits\Macroable`. This means any code can register new methods on them at boot time — without subclassing, without modifying the package.

This is how Flier stays small while supporting domain-specific operations (terminology lookups, SQL on FHIR execution, etc.) through separate modules.

### Registering a macro

```php
use FHIR\Flier\Builder\ResourceBuilder;
use FHIR\Flier\Builder\SearchBuilder;

// In a ServiceProvider::boot()
ResourceBuilder::macro('summarize', function (): string {
    /** @var ResourceBuilder $this */
    return "{$this->getResourceType()} with {$this->name()->value()[0]['family']} " .
           "({$this->birthDate()->value()})";
});

// Usage
echo Flier::resource('Patient', $patient)->summarize();
// → "Patient with Silva (1990-03-15)"
```

The `/** @var ResourceBuilder $this */` docblock tells your IDE what `$this` is inside the closure.

### Guarding registration

If your module is optional (might not always be installed alongside Flier), guard the registration:

```php
private function registerFlierMacros(): void
{
    if (! class_exists(ResourceBuilder::class)) {
        return;
    }

    ResourceBuilder::macro('myMethod', function (): mixed {
        // ...
    });
}
```

### Macro priority

Macros take priority over FHIR property magic. If you register a macro named `status`, calling `->status()` will always invoke the macro — not the `__call` handler. Keep this in mind when naming macros.

### Built-in macros from Fhive modules

| Module | Macro | Available on | Description |
|---|---|---|---|
| `fhive/terminology` | `lookup()` | `ResourceBuilder` | Start a `$lookup` operation on a CodeSystem |
| `fhive/terminology` | `expand()` | `ResourceBuilder` | Expand a ValueSet |
| `fhive/terminology` | `validateCode($code)` | `ResourceBuilder` | Validate a code |
| `fhive/sql-on-fhir` | `run($resources)` | `ResourceBuilder` | Run a ViewDefinition in-memory |

---

## Search parameter sources

Flier includes a source registration system that lets modules declare which `SearchParameter` definitions they own. This is used by `fhive/flier-storage` when indexing resources — but the source API itself is open-source and available to anyone.

### The contract

```php
namespace FHIR\Flier\Contracts;

interface FHIRSearchParameterSource
{
    /** @return list<FHIRSearchParameter> */
    public function forType(string $resourceType): array;
}
```

`FHIRSearchParameter` is a value object with `code`, `type`, and `expression` (a FHIRPath expression).

### Registering a source

Call `Flier::registerSearchParameterSource()` from any module's `boot()` method. This is safe to call multiple times from multiple modules.

```php
use FHIR\Flier\Flier;
use FHIR\Flier\Sources\InMemorySearchParameterSource;

public function boot(): void
{
    Flier::registerSearchParameterSource(
        InMemorySearchParameterSource::only('HospitalBed', [
            new SearchParam(code: 'ward',  type: 'token',   expression: 'HospitalBed.ward'),
            new SearchParam(code: 'floor', type: 'string',  expression: 'HospitalBed.floor'),
            new SearchParam(code: 'date',  type: 'date',    expression: 'HospitalBed.date'),
        ])
    );
}
```

### InMemorySearchParameterSource

The simplest source — holds parameters defined directly in code. Great for tests and for built-in resource types:

```php
use FHIR\Flier\Sources\InMemorySearchParameterSource;

// Single type (most common)
$source = InMemorySearchParameterSource::only('Patient', [
    new SearchParam(code: 'family', type: 'string', expression: 'Patient.name.family'),
    new SearchParam(code: 'gender', type: 'token',  expression: 'Patient.gender'),
]);

// Multiple types at once
$source = new InMemorySearchParameterSource([
    'Patient'     => [...],
    'Observation' => [...],
]);
```

### CompositeSearchParameterSource

`CompositeSearchParameterSource` aggregates multiple sources. Flier registers it as a singleton and binds `FHIRSearchParameterSource` to it. Every call to `Flier::registerSearchParameterSource()` adds to this composite.

When two sources define the same `code` for the same resource type, **the first registered wins**. Register higher-priority sources earlier in the boot sequence.

```php
// In AppServiceProvider::boot() — runs before module providers
Flier::registerSearchParameterSource($hardCodedOverrides); // wins on conflict

// In a module ServiceProvider::boot()
Flier::registerSearchParameterSource($moduleSource); // lower priority
```

### Writing your own source

If your search parameters live in a database, a file, or a remote service, implement `FHIRSearchParameterSource`:

```php
use FHIR\Flier\Contracts\FHIRSearchParameter;
use FHIR\Flier\Contracts\FHIRSearchParameterSource;

class DatabaseSearchParameterSource implements FHIRSearchParameterSource
{
    /** @return list<FHIRSearchParameter> */
    public function forType(string $resourceType): array
    {
        return SearchParameterModel::query()
            ->where('resource_type', $resourceType)
            ->get()
            ->map(fn ($row) => new SearchParam(
                code: $row->code,
                type: $row->type,
                expression: $row->expression,
            ))
            ->all();
    }
}
```

Then register it:

```php
Flier::registerSearchParameterSource(app(DatabaseSearchParameterSource::class));
```

> **FrankenPHP / Octane note:** Sources are registered during `boot()`, before any requests are served. The composite singleton is effectively immutable at runtime. This is intentional and safe — `add()` should never be called during a request.
