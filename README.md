<p align="center">
  <img src="docs/flier.png" alt="Flier" width="320">
</p>

<p align="center">
  <strong>Work with FHIR the Laravel way.</strong>
</p>

<p align="center">
  <a href="https://packagist.org/packages/fhive/flier"><img src="https://img.shields.io/packagist/v/fhive/flier" alt="Latest Release"></a>
  <img src="https://img.shields.io/badge/PHP-8.5%2B-777BB4" alt="PHP 8.5+">
  <img src="https://img.shields.io/badge/Laravel-12%2B-FF2D20" alt="Laravel 12+">
  <img src="https://img.shields.io/badge/FHIR-R4-0078D4" alt="FHIR R4">
  <img src="https://img.shields.io/badge/license-MIT-green" alt="MIT License">
</p>

> **⚠️ Early Development — Not Production Ready**
>
> Flier is under active development. The public API is still stabilizing and **should not be used
> in production** at this time. Breaking changes may occur between releases without prior notice.
> Follow [fhive-app/flier](https://github.com/fhive-app/flier) for stability updates.

---

Healthcare software runs on [FHIR](https://hl7.org/fhir/) — a standard that defines how clinical data (patients, observations, medications, appointments) is structured and exchanged between systems. Every EHR, hospital system, and health API you'll ever integrate with speaks FHIR.

The problem is that FHIR's wire format is verbose JSON with a very specific shape. Building a patient record by hand means assembling deeply nested arrays and hoping you got the property names right. Searching means constructing query strings with arcane modifiers. Editing means either replacing the whole resource or generating a FHIRPath Patch document that most PHP developers have never heard of.

Flier turns all of that into fluent PHP.

```php
// Build a patient
$patient = Flier::resource('Patient')
    ->name([['family' => 'Silva', 'given' => ['João']]])
    ->birthDate('1990-03-15')
    ->gender('male')
    ->toArray();

// Edit with surgical precision
Flier::resource('Patient', $existing)
    ->status()->replace('inactive')
    ->deceased()->delete()
    ->useDriver(new FHIRHttpDriver('https://hapi.fhir.org/baseR4'))
    ->update(); // sends a FHIRPath Patch — no full replace needed

// Search
$url = Flier::search('Patient')
    ->family('Silva')
    ->birthdate('ge1990-01-01')
    ->asUrl('https://hapi.fhir.org/baseR4');
// → "https://hapi.fhir.org/baseR4/Patient?family=Silva&birthdate=ge1990-01-01"
```

---

## What Flier does

- **Build FHIR resources** from scratch using a fluent, magic-method API — no array juggling
- **Edit resources** with add, replace, and delete operations recorded as an immutable log
- **Generate FHIRPath Patch** documents automatically from the operation log
- **Build FHIR search URLs** with a fluent search builder — all parameter types, all modifiers
- **Talk to external FHIR servers** via `FHIRHttpDriver` — `POST`, `PATCH`, `PUT`, `DELETE`, and `GET` search with the correct `application/fhir+json` headers
- **Parse FHIR responses** — `Flier::from($array)` and `Flier::fromBundle($bundle)` give you builders back from whatever the server returned
- **Register search parameter sources** — tell Flier which `SearchParameter` definitions apply to which resource types, so other modules can index and search them

Flier is also **Macroable**. If you're building a module on top of it, you can register your own methods on `ResourceBuilder` and `SearchBuilder` without touching the core.

## What Flier does not do

Flier is deliberately scoped. It does not:

- **Validate FHIR resources** — it won't tell you that a required field is missing or that a code is invalid. Use a validator or a FHIR server for that.
- **Parse FHIRPath expressions** — evaluation of FHIRPath is handled by a separate package (`fhive/path`).
- **Run a search index** — indexing FHIR resources into a queryable table is outside this package's scope.
- **Know about specific FHIR resource types** — it has no hardcoded knowledge of `Patient`, `Observation`, or anything else. Every property is a magic method. You can use it with custom resources just as easily as standard ones.
- **Handle authentication** — bearer tokens, SMART on FHIR scopes, and OAuth are outside its scope.

---

## Installation

```bash
composer require fhive/flier
```

The `FlierServiceProvider` is auto-discovered. Nothing else to configure.

---

## Documentation

Detailed documentation covering every feature is available in English and Portuguese:

- 🇺🇸 **[English guide](docs/guide.md)**
- 🇧🇷 **[Guia em Português](docs/guide.pt-BR.md)**

Topics covered:

- ResourceBuilder — building and editing resources
- SearchBuilder — building search URLs
- PropertyProxy — surgical edits
- Operations — how mutations work
- FHIRPath Patch
- Drivers — HTTP and custom
- Parsing FHIR responses
- Macros
- Search parameter sources

---

## Quick examples

### Creating a resource

```php
use FHIR\Flier\Flier;

$patient = Flier::resource('Patient')
    ->name([['family' => 'Pereira', 'given' => ['Ana']]])
    ->birthDate('1985-07-22')
    ->gender('female')
    ->toArray();
```

### Editing a resource you got from somewhere else

```php
$updated = Flier::from($existingPatient)
    ->telecom([['system' => 'phone', 'value' => '+55 11 99999-0000']])
    ->deceasedBoolean()->delete()
    ->toArray();
```

### Sending a PATCH to a FHIR server

Flier records every edit as an operation and, when you call `update()` with an HTTP driver, converts them into a [FHIRPath Patch](https://hl7.org/fhir/http.html#patch) automatically. You never write the `Parameters` resource by hand.

```php
use FHIR\Flier\Drivers\FHIRHttpDriver;

$driver = new FHIRHttpDriver('https://hapi.fhir.org/baseR4');

Flier::resource('Observation', $existing)
    ->status()->replace('final')
    ->issued()->replace(now()->toIso8601String())
    ->useDriver($driver)
    ->update();
```

### Searching

```php
// Build a URL only
$url = Flier::search('Observation')
    ->subject('Patient/p1')
    ->code('8867-4')
    ->date('ge2024-01-01')
    ->asUrl('https://hapi.fhir.org/baseR4');

// Execute against a server
$bundle = Flier::search('Observation')
    ->subject('Patient/p1')
    ->useDriver(new FHIRHttpDriver('https://hapi.fhir.org/baseR4'))
    ->search();
```

### Parsing a bundle response

```php
$builders = Flier::fromBundle($response->json());

$observations = $builders
    ->filter(fn ($b) => $b->getResourceType() === 'Observation')
    ->map(fn ($b) => $b->getResourceData());
```

### Writing a custom driver

```php
use FHIR\Flier\Builder\Operations\Operation;
use FHIR\Flier\Drivers\ResourceDriver;

class MyFHIRDriver implements ResourceDriver
{
    public function create(string $resourceType, array $data, array $operations): array
    {
        // $data is the current state, $operations are the pending changes
        // apply them, send them — your call
    }

    public function update(string $resourceType, array $data, array $operations): array { ... }
    public function put(string $resourceType, array $data, array $operations): array { ... }
    public function delete(string $resourceType, array $data): array { ... }
}
```

---

## What's next

Flier is young. A few things we want to add:

- **`FHIRConditionalDriver`** — `If-Match` and conditional create/update semantics (FHIR ETags)
- **`FHIRBatchDriver`** — wrap multiple operations into a single FHIR transaction Bundle
- **More SearchBuilder sugar** — chainable composite searches, `_sort`, `_include`, `_revinclude`
- **R5 compatibility** — the builders work with any version's JSON, but we want to document and test R5 patterns explicitly

---

## Testing

```bash
composer test
# or
php artisan test --compact modules/flier/tests/
```

Feature tests for search indexing require PostgreSQL. Everything else runs on SQLite in-memory.

---

## License

MIT — see [LICENSE](LICENSE).
