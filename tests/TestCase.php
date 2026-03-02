<?php

declare(strict_types=1);

namespace FHIR\Flier\Tests;

use Illuminate\Support\Facades\DB;
use Tests\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Usa vars prefixadas FLIER_DB_* (não sobrescritas pelo phpunit.xml).
        // Uses FLIER_DB_* prefixed vars (not overridden by phpunit.xml).
        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql' => [
                'driver' => 'pgsql',
                'host' => env('FLIER_DB_HOST', env('PGSQL_HOST', '127.0.0.1')),
                'port' => env('FLIER_DB_PORT', env('PGSQL_PORT', '5432')),
                'database' => env('FLIER_DB_DATABASE', 'fhive'),
                'username' => env('FLIER_DB_USERNAME', env('PGSQL_USER', 'root')),
                'password' => env('FLIER_DB_PASSWORD', env('PGSQL_PASSWORD', '')),
                'charset' => 'utf8',
                'prefix' => '',
                'prefix_indexes' => true,
                'search_path' => 'public',
                'sslmode' => 'prefer',
            ],
            'projects.enabled' => false,
        ]);

        DB::purge('pgsql');
        DB::setDefaultConnection('pgsql');
    }
}
