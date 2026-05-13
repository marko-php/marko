<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Scope\Exceptions\ScopeConfigurationException;
use Marko\Scope\Exceptions\ScopeContextException;
use Marko\Scope\Exceptions\ScopeStorageException;
use Marko\Scope\Exceptions\UnknownAxisException;
use Marko\Scope\Exceptions\UnknownScopeException;

it('provides UnknownAxisException with axis name and suggestion to register it', function (): void {
    $exception = UnknownAxisException::forAxis('store');

    expect($exception)->toBeInstanceOf(UnknownAxisException::class)
        ->and($exception->getMessage())->toContain('store')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->toContain('register');
});

it('provides UnknownScopeException with axis and path and suggestion', function (): void {
    $exception = UnknownScopeException::forAxisAndPath('store', 'default/en');

    expect($exception)->toBeInstanceOf(UnknownScopeException::class)
        ->and($exception->getMessage())->toContain('store')
        ->and($exception->getMessage())->toContain('default/en')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});

it('provides ScopeConfigurationException for malformed scope config', function (): void {
    $exception = ScopeConfigurationException::malformedConfig('store', 'missing label key');

    expect($exception)->toBeInstanceOf(ScopeConfigurationException::class)
        ->and($exception->getMessage())->toContain('store')
        ->and($exception->getMessage())->toContain('missing label key')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});

it('provides ScopeContextException when reading context for an unset axis or invalid path', function (): void {
    $exception = ScopeContextException::axisNotSet('currency');

    expect($exception)->toBeInstanceOf(ScopeContextException::class)
        ->and($exception->getMessage())->toContain('currency')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});

it('provides ScopeStorageException when the scopes column is missing on save', function (): void {
    $exception = ScopeStorageException::missingColumn('scopes', 'products');

    expect($exception)->toBeInstanceOf(ScopeStorageException::class)
        ->and($exception->getMessage())->toContain('scopes')
        ->and($exception->getMessage())->toContain('products')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});

it('extends MarkoException for all scope exceptions', function (): void {
    expect(UnknownAxisException::forAxis('store'))->toBeInstanceOf(MarkoException::class)
        ->and(UnknownScopeException::forAxisAndPath('store', 'default'))->toBeInstanceOf(MarkoException::class)
        ->and(ScopeConfigurationException::malformedConfig('store', 'reason'))->toBeInstanceOf(MarkoException::class)
        ->and(ScopeContextException::axisNotSet('currency'))->toBeInstanceOf(MarkoException::class)
        ->and(ScopeStorageException::missingColumn('scopes', 'products'))->toBeInstanceOf(MarkoException::class);
});
