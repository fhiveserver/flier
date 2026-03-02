<?php

declare(strict_types=1);

namespace FHIR\Flier\Drivers;

use FHIR\Flier\Builder\SearchParam;
use FHIR\Flier\Patch\FHIRPathPatchDriver;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Driver HTTP FHIR usando Laravel Http facade.
 * FHIR HTTP driver using Laravel Http facade.
 *
 * Open-source. Implementa ResourceDriver e SearchDriver.
 * Open-source. Implements ResourceDriver and SearchDriver.
 *
 * Uso / Usage:
 *   $driver = new FHIRHttpDriver('https://hapi.fhir.org/baseR4');
 *
 *   // POST — cria com operações / POST — creates with operations
 *   Flier::resource('Patient')->gender('male')->useDriver($driver)->create();
 *
 *   // PATCH — FHIRPath Patch via update() / PATCH — FHIRPath Patch via update()
 *   Flier::resource('Patient', $data)->birthDate()->delete()->useDriver($driver)->update();
 */
final class FHIRHttpDriver implements ResourceDriver, SearchDriver
{
    public function __construct(private readonly string $baseUrl) {}

    // ——————————————————————————————————————————————————————————————————
    // ResourceDriver
    // ——————————————————————————————————————————————————————————————————

    /** {@inheritdoc} */
    public function create(string $resourceType, array $data, array $operations): array
    {
        $payload = (new ArrayResourceDriver)->create($resourceType, $data, $operations);

        return $this->client()
            ->post("/{$resourceType}", $payload)
            ->throw()
            ->json();
    }

    /** {@inheritdoc} */
    public function update(string $resourceType, array $data, array $operations): array
    {
        $id = $data['id'] ?? throw new InvalidArgumentException(
            "Resource must contain 'id' for PATCH.",
        );

        $patch = app(FHIRPathPatchDriver::class)->generate($resourceType, $operations);

        return $this->client()
            ->patch("/{$resourceType}/{$id}", $patch)
            ->throw()
            ->json();
    }

    /** {@inheritdoc} */
    public function put(string $resourceType, array $data, array $operations): array
    {
        $payload = (new ArrayResourceDriver)->put($resourceType, $data, $operations);
        $id = $payload['id'] ?? throw new InvalidArgumentException(
            "Resource must contain 'id' for PUT.",
        );

        return $this->client()
            ->put("/{$resourceType}/{$id}", $payload)
            ->throw()
            ->json();
    }

    /** {@inheritdoc} */
    public function delete(string $resourceType, array $data): array
    {
        $id = $data['id'] ?? throw new InvalidArgumentException(
            "Resource must contain 'id' for DELETE.",
        );

        $this->client()
            ->delete("/{$resourceType}/{$id}")
            ->throw();

        return [];
    }

    // ——————————————————————————————————————————————————————————————————
    // SearchDriver
    // ——————————————————————————————————————————————————————————————————

    /**
     * @param  list<SearchParam>  $params
     */
    public function search(string $resourceType, array $params): array
    {
        $query = collect($params)
            ->mapWithKeys(fn (SearchParam $p) => [
                $p->modifier ? "{$p->code}:{$p->modifier}" : $p->code => $p->rawValue,
            ])
            ->all();

        return $this->client()
            ->get("/{$resourceType}", $query)
            ->throw()
            ->json();
    }

    // ——————————————————————————————————————————————————————————————————
    // Interno / Internal
    // ——————————————————————————————————————————————————————————————————

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'Accept' => 'application/fhir+json',
            'Content-Type' => 'application/fhir+json',
        ])->baseUrl(rtrim($this->baseUrl, '/'));
    }
}
