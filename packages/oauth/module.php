<?php

declare(strict_types=1);

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

return [
    'bindings' => [
        OAuthAccessTokenRepositoryInterface::class => OAuthAccessTokenRepository::class,
        OAuthApprovalRepositoryInterface::class => OAuthApprovalRepository::class,
        OAuthAuthCodeRepositoryInterface::class => OAuthAuthCodeRepository::class,
        OAuthClientRepositoryInterface::class => OAuthClientRepository::class,
        OAuthRefreshTokenRepositoryInterface::class => OAuthRefreshTokenRepository::class,
        OAuthScopeRepositoryInterface::class => OAuthScopeRepository::class,
    ],
];
