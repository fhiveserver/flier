<?php

declare(strict_types=1);

use FHIR\Flier\Builder\ResourceBuilder;
use FHIR\Flier\Builder\SearchBuilder;

// — ResourceBuilder macros —————————————————————————————————————————

it('ResourceBuilder accepts and calls a macro', function () {
    ResourceBuilder::macro('testMacro', function (): string {
        return 'macro-result';
    });

    $result = (new ResourceBuilder('Patient'))->testMacro();

    expect($result)->toBe('macro-result');
});

it('ResourceBuilder macro receives $this as the builder instance', function () {
    ResourceBuilder::macro('resourceTypeMacro', function (): string {
        /** @var ResourceBuilder $this */
        return $this->getResourceType();
    });

    $result = (new ResourceBuilder('Observation'))->resourceTypeMacro();

    expect($result)->toBe('Observation');
});

it('ResourceBuilder macro does not interfere with FHIR property magic', function () {
    ResourceBuilder::macro('myMacro', fn () => 'macro');

    $builder = new ResourceBuilder('Patient');

    // FHIR property magic still works
    $builder->gender('male');
    expect($builder->getOperations())->toHaveCount(1);

    // And macro still works
    expect($builder->myMacro())->toBe('macro');
});

// — SearchBuilder macros ————————————————————————————————————————————

it('SearchBuilder accepts and calls a macro', function () {
    SearchBuilder::macro('searchTestMacro', function (): string {
        return 'search-macro-result';
    });

    $result = (new SearchBuilder('Patient'))->searchTestMacro();

    expect($result)->toBe('search-macro-result');
});

it('SearchBuilder macro does not interfere with FHIR param magic', function () {
    SearchBuilder::macro('mySearchMacro', fn () => 'ok');

    $builder = new SearchBuilder('Patient');
    $builder->family('Smith');

    expect($builder->getParams())->toHaveCount(1);
    expect($builder->mySearchMacro())->toBe('ok');
});
