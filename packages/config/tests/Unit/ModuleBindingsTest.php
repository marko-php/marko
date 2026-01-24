<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;

it('module.php binds ConfigRepositoryInterface to ConfigRepository class', function () {
    $moduleConfig = require __DIR__ . '/../../module.php';

    expect($moduleConfig['bindings'])->toHaveKey(ConfigRepositoryInterface::class)
        ->and($moduleConfig['bindings'][ConfigRepositoryInterface::class])->toBe(ConfigRepository::class);
});
