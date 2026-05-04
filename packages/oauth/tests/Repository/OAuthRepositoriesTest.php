<?php

declare(strict_types=1);

use Marko\Database\Repository\RepositoryInterface;
use Marko\OAuth\Repository\OAuthAccessTokenRepository;
use Marko\OAuth\Repository\OAuthAccessTokenRepositoryInterface;
use Marko\OAuth\Repository\OAuthApprovalRepository;
use Marko\OAuth\Repository\OAuthApprovalRepositoryInterface;
use Marko\OAuth\Repository\OAuthAuthCodeRepository;
use Marko\OAuth\Repository\OAuthAuthCodeRepositoryInterface;
use Marko\OAuth\Repository\OAuthClientRepository;
use Marko\OAuth\Repository\OAuthClientRepositoryInterface;
use Marko\OAuth\Repository\OAuthRefreshTokenRepository;
use Marko\OAuth\Repository\OAuthRefreshTokenRepositoryInterface;
use Marko\OAuth\Repository\OAuthScopeRepository;
use Marko\OAuth\Repository\OAuthScopeRepositoryInterface;

it('defines repository interfaces for oauth entities', function (string $interface): void {
    expect(interface_exists($interface))->toBeTrue()
        ->and(in_array(RepositoryInterface::class, class_implements($interface), true))->toBeTrue();
})->with([
    OAuthClientRepositoryInterface::class,
    OAuthAuthCodeRepositoryInterface::class,
    OAuthAccessTokenRepositoryInterface::class,
    OAuthRefreshTokenRepositoryInterface::class,
    OAuthApprovalRepositoryInterface::class,
    OAuthScopeRepositoryInterface::class,
]);

it('binds repository interfaces to concrete repositories', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module['bindings'][OAuthClientRepositoryInterface::class])->toBe(OAuthClientRepository::class)
        ->and($module['bindings'][OAuthAuthCodeRepositoryInterface::class])->toBe(OAuthAuthCodeRepository::class)
        ->and($module['bindings'][OAuthAccessTokenRepositoryInterface::class])->toBe(OAuthAccessTokenRepository::class)
        ->and($module['bindings'][OAuthRefreshTokenRepositoryInterface::class])->toBe(
            OAuthRefreshTokenRepository::class,
        )
        ->and($module['bindings'][OAuthApprovalRepositoryInterface::class])->toBe(OAuthApprovalRepository::class)
        ->and($module['bindings'][OAuthScopeRepositoryInterface::class])->toBe(OAuthScopeRepository::class);
});
