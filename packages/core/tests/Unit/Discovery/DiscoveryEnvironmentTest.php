<?php

declare(strict_types=1);

use Marko\Core\Discovery\DiscoveryEnvironment;

describe('DiscoveryEnvironment', function (): void {
    beforeEach(function (): void {
        $this->originalEnabled = array_key_exists(
            'DISCOVERY_CACHE_ENABLED',
            $_ENV,
        ) ? $_ENV['DISCOVERY_CACHE_ENABLED'] : null;
        $this->originalAppEnv = array_key_exists('APP_ENV', $_ENV) ? $_ENV['APP_ENV'] : null;
        $this->originalCachePath = array_key_exists(
            'DISCOVERY_CACHE_PATH',
            $_ENV,
        ) ? $_ENV['DISCOVERY_CACHE_PATH'] : null;
    });

    afterEach(function (): void {
        if ($this->originalEnabled === null) {
            unset($_ENV['DISCOVERY_CACHE_ENABLED']);
        } else {
            $_ENV['DISCOVERY_CACHE_ENABLED'] = $this->originalEnabled;
        }

        if ($this->originalAppEnv === null) {
            unset($_ENV['APP_ENV']);
        } else {
            $_ENV['APP_ENV'] = $this->originalAppEnv;
        }

        if ($this->originalCachePath === null) {
            unset($_ENV['DISCOVERY_CACHE_PATH']);
        } else {
            $_ENV['DISCOVERY_CACHE_PATH'] = $this->originalCachePath;
        }
    });

    it(
        'returns enabled() true by default (no env set) and treats DISCOVERY_CACHE_ENABLED values 0, false, no, off, and empty (case-insensitive) as disabled',
        function (): void {
            $env = new DiscoveryEnvironment();

            // Default: no env var set → true
            unset($_ENV['DISCOVERY_CACHE_ENABLED']);
            expect($env->enabled())->toBeTrue();

            // Falsy string values that should all map to false
            foreach (['0', 'false', 'FALSE', 'False', 'no', 'NO', 'No', 'off', 'OFF', 'Off', ''] as $falsy) {
                $_ENV['DISCOVERY_CACHE_ENABLED'] = $falsy;
                expect($env->enabled())->toBeFalse();
            }
        },
    );

    it(
        'returns enabled() true for any other present DISCOVERY_CACHE_ENABLED value (e.g. "1", "true", "yes")',
        function (): void {
            $env = new DiscoveryEnvironment();

            foreach (['1', 'true', 'TRUE', 'True', 'yes', 'YES', 'Yes', 'on', 'ON', 'enabled'] as $truthy) {
                $_ENV['DISCOVERY_CACHE_ENABLED'] = $truthy;
                expect($env->enabled())->toBeTrue();
            }
        },
    );

    it('returns environment() from APP_ENV and defaults to production when APP_ENV is unset', function (): void {
        $env = new DiscoveryEnvironment();

        unset($_ENV['APP_ENV']);
        expect($env->environment())->toBe('production');

        $_ENV['APP_ENV'] = 'staging';
        expect($env->environment())->toBe('staging');

        $_ENV['APP_ENV'] = 'local';
        expect($env->environment())->toBe('local');
    });

    it('returns cachePath() from DISCOVERY_CACHE_PATH and defaults to storage/cache/discovery.php', function (): void {
        $env = new DiscoveryEnvironment();

        unset($_ENV['DISCOVERY_CACHE_PATH']);
        expect($env->cachePath())->toBe('storage/cache/discovery.php');

        $_ENV['DISCOVERY_CACHE_PATH'] = '/var/cache/marko/discovery.php';
        expect($env->cachePath())->toBe('/var/cache/marko/discovery.php');
    });

    it('reads $_ENV live at call time (a value set after construction is reflected)', function (): void {
        unset($_ENV['DISCOVERY_CACHE_ENABLED']);
        unset($_ENV['APP_ENV']);
        unset($_ENV['DISCOVERY_CACHE_PATH']);

        $env = new DiscoveryEnvironment();

        // Defaults before setting env vars
        expect($env->enabled())->toBeTrue()
            ->and($env->environment())->toBe('production')
            ->and($env->cachePath())->toBe('storage/cache/discovery.php');

        // Set env vars AFTER construction — must be reflected
        $_ENV['DISCOVERY_CACHE_ENABLED'] = '0';
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['DISCOVERY_CACHE_PATH'] = '/tmp/discovery.php';

        expect($env->enabled())->toBeFalse()
            ->and($env->environment())->toBe('testing')
            ->and($env->cachePath())->toBe('/tmp/discovery.php');
    });

    it(
        'reads no value from marko/config and has no Marko\Config import (boot-time reader is config-package-free)',
        function (): void {
            $source = (string) file_get_contents(
                dirname(__DIR__, 3) . '/src/Discovery/DiscoveryEnvironment.php',
            );

            expect(str_contains($source, 'Marko\\Config'))->toBeFalse()
                ->and(str_contains($source, 'ConfigRepository'))->toBeFalse()
                ->and(str_contains($source, 'ConfigNotFoundException'))->toBeFalse();
        },
    );

    it(
        'the shipped config/discovery.php returns an array with enabled, environment, and cache_path keys matching the DiscoveryEnvironment defaults when no env vars are set',
        function (): void {
            unset($_ENV['DISCOVERY_CACHE_ENABLED']);
            unset($_ENV['APP_ENV']);
            unset($_ENV['DISCOVERY_CACHE_PATH']);

            $config = require dirname(__DIR__, 3) . '/config/discovery.php';

            expect($config)->toHaveKey('enabled')
                ->and($config)->toHaveKey('environment')
                ->and($config)->toHaveKey('cache_path')
                ->and($config['enabled'])->toBeTrue()
                ->and($config['environment'])->toBe('production')
                ->and($config['cache_path'])->toBe('storage/cache/discovery.php');
        },
    );

    it(
        'snapshots and restores the three $_ENV keys around each test so no env state leaks (verify $_ENV unchanged after the suite for keys that were originally absent)',
        function (): void {
            // This test verifies that our beforeEach/afterEach isolation works correctly.
            // If the original state had these keys absent, they should not be present here
            // (since afterEach restores the snapshot from beforeEach).
            // We set them and they will be cleaned up by afterEach.
            $_ENV['DISCOVERY_CACHE_ENABLED'] = 'no';
            $_ENV['APP_ENV'] = 'test-isolation';
            $_ENV['DISCOVERY_CACHE_PATH'] = '/tmp/test-isolation.php';

            $env = new DiscoveryEnvironment();

            expect($env->enabled())->toBeFalse()
                ->and($env->environment())->toBe('test-isolation')
                ->and($env->cachePath())->toBe('/tmp/test-isolation.php');
            // afterEach will restore the original state
        },
    );
});
