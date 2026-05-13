<?php

declare(strict_types=1);

use Marko\Scope\Axis\ScopeAxis;
use Marko\Scope\Hierarchy\ScopeHierarchy;

it('creates a ScopeAxis with name and hierarchy reference', function (): void {
    $hierarchy = new ScopeHierarchy();
    $axis = new ScopeAxis(name: 'geo', hierarchy: $hierarchy);

    expect($axis->name)->toBe('geo')
        ->and($axis->hierarchy)->toBe($hierarchy);
});
