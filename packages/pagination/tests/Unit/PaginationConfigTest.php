<?php

declare(strict_types=1);

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Pagination\Config\PaginationConfig;
use Marko\Testing\Fake\FakeConfigRepository;

it('uses FakeConfigRepository in PaginationConfigTest', function () {
    $config = new FakeConfigRepository(['pagination.per_page' => 15]);

    expect($config)->toBeInstanceOf(FakeConfigRepository::class);
});

it('loads per_page from config', function () {
    $config = new PaginationConfig(new FakeConfigRepository([
        'pagination.per_page' => 25,
        'pagination.max_per_page' => 100,
    ]));

    expect($config->perPage())->toBe(25);
});

it('loads max_per_page from config', function () {
    $config = new PaginationConfig(new FakeConfigRepository([
        'pagination.per_page' => 15,
        'pagination.max_per_page' => 200,
    ]));

    expect($config->maxPerPage())->toBe(200);
});

it('clamps requested perPage to max_per_page', function () {
    $config = new PaginationConfig(new FakeConfigRepository([
        'pagination.per_page' => 15,
        'pagination.max_per_page' => 100,
    ]));

    expect($config->clampPerPage(200))->toBe(100)
        ->and($config->clampPerPage(50))->toBe(50)
        ->and($config->clampPerPage(100))->toBe(100);
});

it('clamps perPage to 1 minimum', function () {
    $config = new PaginationConfig(new FakeConfigRepository([
        'pagination.per_page' => 15,
        'pagination.max_per_page' => 100,
    ]));

    expect($config->clampPerPage(0))->toBe(1)
        ->and($config->clampPerPage(-5))->toBe(1);
});

it('throws ConfigNotFoundException when per_page is missing', function () {
    $config = new PaginationConfig(new FakeConfigRepository([
        'pagination.max_per_page' => 100,
    ]));

    $config->perPage();
})->throws(ConfigNotFoundException::class);

it('throws ConfigNotFoundException when max_per_page is missing', function () {
    $config = new PaginationConfig(new FakeConfigRepository([
        'pagination.per_page' => 15,
    ]));

    $config->maxPerPage();
})->throws(ConfigNotFoundException::class);

it('provides default config file with per_page and max_per_page', function () {
    $configFile = require dirname(__DIR__, 2) . '/config/pagination.php';

    expect($configFile)->toBeArray()
        ->and($configFile)->toHaveKey('per_page')
        ->and($configFile)->toHaveKey('max_per_page')
        ->and($configFile['per_page'])->toBe(15)
        ->and($configFile['max_per_page'])->toBe(100);
});
