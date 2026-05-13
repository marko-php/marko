<?php

declare(strict_types=1);

use Marko\Scope\Exceptions\ScopeConfigurationException;
use Marko\Scope\Exceptions\UnknownScopeException;
use Marko\Scope\Hierarchy\ScopeHierarchy;

it('builds a hierarchy from a nested array of paths', function (): void {
    $hierarchy = ScopeHierarchy::fromPaths(['eu', 'eu.de', 'eu.fr', 'us', 'us.ny', 'us.ca']);

    expect($hierarchy)->toBeInstanceOf(ScopeHierarchy::class);
});

it('walks up from a deep path to root returning the path and all ancestors in order', function (): void {
    $hierarchy = ScopeHierarchy::fromPaths(['eu', 'eu.de', 'eu.de.berlin']);

    expect($hierarchy->walkUp('eu.de.berlin'))->toBe(['eu.de.berlin', 'eu.de', 'eu']);
});

it('returns true from exists for known paths and false for unknown', function (): void {
    $hierarchy = ScopeHierarchy::fromPaths(['eu', 'eu.de', 'us']);

    expect($hierarchy->exists('eu'))->toBeTrue()
        ->and($hierarchy->exists('eu.de'))->toBeTrue()
        ->and($hierarchy->exists('us'))->toBeTrue()
        ->and($hierarchy->exists('eu.fr'))->toBeFalse()
        ->and($hierarchy->exists('unknown'))->toBeFalse();
});

it('identifies parent-child relationships via isAncestor', function (): void {
    $hierarchy = ScopeHierarchy::fromPaths(['eu', 'eu.de', 'eu.de.berlin', 'us']);

    expect($hierarchy->isAncestor(ancestor: 'eu', descendant: 'eu.de'))->toBeTrue()
        ->and($hierarchy->isAncestor(ancestor: 'eu', descendant: 'eu.de.berlin'))->toBeTrue()
        ->and($hierarchy->isAncestor(ancestor: 'eu.de', descendant: 'eu.de.berlin'))->toBeTrue()
        ->and($hierarchy->isAncestor(ancestor: 'eu.de', descendant: 'eu'))->toBeFalse()
        ->and($hierarchy->isAncestor(ancestor: 'eu', descendant: 'us'))->toBeFalse()
        ->and($hierarchy->isAncestor(ancestor: 'eu', descendant: 'eu'))->toBeFalse();
});

it('throws UnknownScopeException when walking from an unknown path', function (): void {
    $hierarchy = ScopeHierarchy::fromPaths(['eu', 'eu.de']);

    expect(fn () => $hierarchy->walkUp('eu.fr'))->toThrow(UnknownScopeException::class);
});

it('rejects duplicate path declarations at construction time', function (): void {
    expect(fn () => ScopeHierarchy::fromPaths(['eu', 'eu.de', 'eu']))->toThrow(ScopeConfigurationException::class);
});

it('lists all paths in declaration order', function (): void {
    $paths = ['us', 'eu', 'eu.de', 'eu.fr', 'us.ny'];
    $hierarchy = ScopeHierarchy::fromPaths($paths);

    expect($hierarchy->paths())->toBe($paths);
});
