<?php

declare(strict_types=1);

use FHIR\Flier\FlierServiceProvider;

it('service provider instantiates', function () {
    expect(new FlierServiceProvider(app()))->toBeInstanceOf(FlierServiceProvider::class);
});

it('service provider registers without errors', function () {
    $provider = new FlierServiceProvider(app());
    $provider->register();
    $provider->boot();

    expect(true)->toBeTrue();
});
