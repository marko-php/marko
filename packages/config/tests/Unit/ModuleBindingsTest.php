<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;

it('module.php binds ConfigRepositoryInterface to a factory closure', function () {
    $moduleConfig = require __DIR__ . '/../../module.php';

    expect($moduleConfig['bindings'])->toHaveKey(ConfigRepositoryInterface::class)
        ->and($moduleConfig['bindings'][ConfigRepositoryInterface::class])->toBeInstanceOf(Closure::class);
});
