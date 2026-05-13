<?php

declare(strict_types=1);

use Marko\Scope\Exceptions\ScopeConfigurationException;
use Marko\Scope\Scope;

it('creates a Scope with axis name and path', function (): void {
    $scope = new Scope(axisName: 'geo', path: 'eu.de');

    expect($scope->axisName)->toBe('geo')
        ->and($scope->path)->toBe('eu.de');
});

it('parses a scope string "geo:eu.de" via Scope::fromString', function (): void {
    $scope = Scope::fromString('geo:eu.de');

    expect($scope->axisName)->toBe('geo')
        ->and($scope->path)->toBe('eu.de');
});

it('throws ScopeConfigurationException for malformed scope strings', function (): void {
    expect(fn () => Scope::fromString('invalid-no-colon'))
        ->toThrow(ScopeConfigurationException::class);
});

it('formats a Scope back to "axis:path" via Scope::toString', function (): void {
    $scope = new Scope(axisName: 'geo', path: 'eu.de');

    expect($scope->toString())->toBe('geo:eu.de');
});

it('considers two Scope instances equal when axis and path match', function (): void {
    $a = new Scope(axisName: 'geo', path: 'eu.de');
    $b = new Scope(axisName: 'geo', path: 'eu.de');

    expect($a->equals($b))->toBeTrue();
});
