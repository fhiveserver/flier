<?php

declare(strict_types=1);

namespace FHIVE\Flier\Tests;

use FHIVE\Flier\FlierServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * PT: TestCase para o módulo Flier (standalone OSS).
 *     Usa Orchestra Testbench — pode rodar sem o servidor.
 *     DB configurado pelo phpunit.xml.
 * EN: TestCase for the Flier module (standalone OSS).
 *     Uses Orchestra Testbench — can run without the servidor.
 *     DB configured by phpunit.xml.
 */
abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [FlierServiceProvider::class];
    }
}
