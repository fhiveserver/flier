<?php

declare(strict_types=1);

use FHIR\Core\Models\Resource;

// ── Flier indexer — basic index creation ─────────────────────────────

describe('Flier indexer', function () {
    it('indexes a newly created Patient resource without errors', function () {
        $patient = $this->createPatient();

        // Flier indexes on model save — verify the resource persists
        expect(Resource::find($patient->id))->not->toBeNull();
    });

    it('soft-deleted resources are excluded from the active resource set', function () {
        $patient = $this->createPatient();
        $patient->delete();

        expect(Resource::find($patient->id))->toBeNull();
        expect(Resource::withTrashed()->find($patient->id))->not->toBeNull();
    });
});
