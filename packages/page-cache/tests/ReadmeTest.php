<?php

declare(strict_types=1);

it('documents CacheTagProviderInterface with a code example in the Usage section', function (): void {
    $readme = file_get_contents(dirname(__DIR__) . '/README.md');

    expect($readme)->toContain('CacheTagProviderInterface')
        ->and($readme)->toContain('## Usage')
        ->and(strpos($readme, 'CacheTagProviderInterface'))->toBeGreaterThan(strpos($readme, '## Usage'));
});

it('documents the provider parameter on the Cacheable attribute', function (): void {
    $readme = file_get_contents(dirname(__DIR__) . '/README.md');

    expect($readme)->toContain('provider:')
        ->and($readme)->toContain('ProductTagProvider');
});

it('documents IdentityInterface with an entity example in the Usage section', function (): void {
    $readme = file_get_contents(dirname(__DIR__) . '/README.md');

    expect($readme)->toContain('IdentityInterface')
        ->and($readme)->toContain('getIdentities')
        ->and($readme)->toContain('## Usage')
        ->and(strpos($readme, 'IdentityInterface'))->toBeGreaterThan(strpos($readme, '## Usage'));
});

it('points users to marko/page-cache-entity for entity auto-purge', function (): void {
    $readme = file_get_contents(dirname(__DIR__) . '/README.md');

    expect($readme)->toContain('marko/page-cache-entity');
});

it('lists CacheTagProviderInterface, IdentityInterface, and the new Cacheable signature in the API Reference', function (): void {
    $readme = file_get_contents(dirname(__DIR__) . '/README.md');

    $apiRefPos = strpos($readme, '## API Reference');

    expect($apiRefPos)->not->toBeFalse()
        ->and(strpos($readme, 'CacheTagProviderInterface', $apiRefPos))->toBeGreaterThan($apiRefPos)
        ->and(strpos($readme, 'IdentityInterface', $apiRefPos))->toBeGreaterThan($apiRefPos)
        ->and(strpos($readme, '?string $provider', $apiRefPos))->toBeGreaterThan($apiRefPos);
});
