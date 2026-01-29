<?php

declare(strict_types=1);

use Marko\Env\EnvLoader;

beforeEach(function () {
    // Store original env state
    $this->originalEnv = $_ENV;

    // Create temp directory for test fixtures
    $this->tempDir = sys_get_temp_dir() . '/marko-env-test-' . uniqid();
    mkdir($this->tempDir, 0777, true);
});

afterEach(function () {
    // Restore original env state
    $_ENV = $this->originalEnv;

    // Clean up any env vars we set via putenv
    foreach (['TEST_VAR', 'QUOTED_VAR', 'SINGLE_QUOTED', 'SPACED_VAR', 'EMPTY_VAR', 'EXISTING_VAR', 'APP_ENV', 'DB_HOST', 'DB_PORT'] as $var) {
        putenv($var);
    }

    // Clean up temp directory
    if (is_dir($this->tempDir)) {
        $files = glob($this->tempDir . '/{,.}*', GLOB_BRACE);
        foreach ($files ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->tempDir);
    }
});

it('loads simple key-value pairs from .env file', function () {
    file_put_contents($this->tempDir . '/.env', "TEST_VAR=hello\nAPP_ENV=testing");

    $loader = new EnvLoader();
    $loader->load($this->tempDir);

    expect($_ENV['TEST_VAR'])->toBe('hello')
        ->and($_ENV['APP_ENV'])->toBe('testing')
        ->and(getenv('TEST_VAR'))->toBe('hello')
        ->and(getenv('APP_ENV'))->toBe('testing');
});

it('handles double-quoted values', function () {
    file_put_contents($this->tempDir . '/.env', 'QUOTED_VAR="hello world"');

    $loader = new EnvLoader();
    $loader->load($this->tempDir);

    expect($_ENV['QUOTED_VAR'])->toBe('hello world');
});

it('handles single-quoted values', function () {
    file_put_contents($this->tempDir . '/.env', "SINGLE_QUOTED='hello world'");

    $loader = new EnvLoader();
    $loader->load($this->tempDir);

    expect($_ENV['SINGLE_QUOTED'])->toBe('hello world');
});

it('trims whitespace from names and values', function () {
    file_put_contents($this->tempDir . '/.env', '  SPACED_VAR  =  value  ');

    $loader = new EnvLoader();
    $loader->load($this->tempDir);

    expect($_ENV['SPACED_VAR'])->toBe('value');
});

it('skips comment lines', function () {
    file_put_contents($this->tempDir . '/.env', "# This is a comment\nTEST_VAR=value\n  # Another comment");

    $loader = new EnvLoader();
    $loader->load($this->tempDir);

    expect($_ENV['TEST_VAR'])->toBe('value')
        ->and(isset($_ENV['#']))->toBeFalse();
});

it('skips lines without equals sign', function () {
    file_put_contents($this->tempDir . '/.env', "invalid line\nTEST_VAR=value");

    $loader = new EnvLoader();
    $loader->load($this->tempDir);

    expect($_ENV['TEST_VAR'])->toBe('value');
});

it('handles empty values', function () {
    file_put_contents($this->tempDir . '/.env', 'EMPTY_VAR=');

    $loader = new EnvLoader();
    $loader->load($this->tempDir);

    expect($_ENV['EMPTY_VAR'])->toBe('');
});

it('handles values with equals signs', function () {
    file_put_contents($this->tempDir . '/.env', 'DB_URL=mysql://user:pass@host/db?charset=utf8');

    $loader = new EnvLoader();
    $loader->load($this->tempDir);

    expect($_ENV['DB_URL'])->toBe('mysql://user:pass@host/db?charset=utf8');
});

it('does not overwrite existing $_ENV variables', function () {
    $_ENV['EXISTING_VAR'] = 'original';

    file_put_contents($this->tempDir . '/.env', 'EXISTING_VAR=overwritten');

    $loader = new EnvLoader();
    $loader->load($this->tempDir);

    expect($_ENV['EXISTING_VAR'])->toBe('original');
});

it('does not overwrite existing getenv variables', function () {
    putenv('EXISTING_VAR=original');

    file_put_contents($this->tempDir . '/.env', 'EXISTING_VAR=overwritten');

    $loader = new EnvLoader();
    $loader->load($this->tempDir);

    expect(getenv('EXISTING_VAR'))->toBe('original');
});

it('does nothing when .env file does not exist', function () {
    $loader = new EnvLoader();
    $loader->load($this->tempDir); // No .env file exists

    // Should not throw, should not modify $_ENV
    expect(true)->toBeTrue();
});

it('handles multiple variables', function () {
    $content = <<<'ENV'
APP_ENV=production
DB_HOST=localhost
DB_PORT=3306
ENV;

    file_put_contents($this->tempDir . '/.env', $content);

    $loader = new EnvLoader();
    $loader->load($this->tempDir);

    expect($_ENV['APP_ENV'])->toBe('production')
        ->and($_ENV['DB_HOST'])->toBe('localhost')
        ->and($_ENV['DB_PORT'])->toBe('3306');
});

it('skips empty lines', function () {
    $content = <<<'ENV'
TEST_VAR=value1

APP_ENV=testing

ENV;

    file_put_contents($this->tempDir . '/.env', $content);

    $loader = new EnvLoader();
    $loader->load($this->tempDir);

    expect($_ENV['TEST_VAR'])->toBe('value1')
        ->and($_ENV['APP_ENV'])->toBe('testing');
});
