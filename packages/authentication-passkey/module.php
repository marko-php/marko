<?php

declare(strict_types=1);

use Marko\AuthenticationPasskey\Contracts\PasskeyCredentialRepositoryInterface;
use Marko\AuthenticationPasskey\Contracts\PasskeyVerifierInterface;
use Marko\AuthenticationPasskey\Repository\PasskeyCredentialRepository;
use Marko\AuthenticationPasskey\Verifier\LbuchsWebAuthnVerifier;

return [
    'bindings' => [
        PasskeyVerifierInterface::class => LbuchsWebAuthnVerifier::class,
        PasskeyCredentialRepositoryInterface::class => PasskeyCredentialRepository::class,
    ],
];
