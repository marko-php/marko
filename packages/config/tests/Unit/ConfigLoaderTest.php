<?php

declare(strict_types=1);

use Marko\Config\ConfigLoader;
use Marko\Config\Exceptions\ConfigLoadException;

it('loads a valid config file and returns the array', function () {
    $loader = new ConfigLoader();

    $configPath = __DIR__ . '/fixtures/valid-config.php';

    $result = $loader->load($configPath);

    expect($result)->toBe([
        'database' => [
            'host' => 'localhost',
            'port' => 3306,
        ],
    ]);
});

it('throws ConfigLoadException when file does not exist', function () {
    $loader = new ConfigLoader();

    $loader->load('/non/existent/path/config.php');
})->throws(ConfigLoadException::class);

it('throws ConfigLoadException when file returns non-array', function () {
    $loader = new ConfigLoader();

    $configPath = __DIR__ . '/fixtures/returns-string.php';

    $loader->load($configPath);
})->throws(ConfigLoadException::class);

it('throws ConfigLoadException with helpful message for PHP syntax errors', function () {
    $loader = new ConfigLoader();

    $configPath = __DIR__ . '/fixtures/syntax-error.php';

    $loader->load($configPath);
})->throws(ConfigLoadException::class);

it('loadIfExists returns array when file exists', function () {
    $loader = new ConfigLoader();

    $configPath = __DIR__ . '/fixtures/valid-config.php';

    $result = $loader->loadIfExists($configPath);

    expect($result)->toBe([
        'database' => [
            'host' => 'localhost',
            'port' => 3306,
        ],
    ]);
});

it('loadIfExists returns null when file does not exist', function () {
    $loader = new ConfigLoader();

    $result = $loader->loadIfExists('/non/existent/path/config.php');

    expect($result)->toBeNull();
});
