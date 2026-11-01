<?php

declare(strict_types=1);

use Marko\AuthenticationTwoFactor\Contracts\TotpInterface;
use Marko\AuthenticationTwoFactor\Contracts\TwoFactorManagerInterface;
use Marko\AuthenticationTwoFactor\Totp\OtphpTotp;
use Marko\AuthenticationTwoFactor\TwoFactorManager;

return [
    'bindings' => [
        TotpInterface::class => OtphpTotp::class,
        TwoFactorManagerInterface::class => TwoFactorManager::class,
    ],
];
