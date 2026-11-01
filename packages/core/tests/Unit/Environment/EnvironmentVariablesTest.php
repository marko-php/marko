<?php

declare(strict_types=1);

use Marko\Core\Discovery\DiscoveryEnvironment;
use Marko\Core\Environment\AppEnvironment;
use Marko\Core\Environment\EnvironmentVariables;
use Marko\Core\Exceptions\EnvironmentException;

const ENVIRONMENT_VARIABLES_TEST_NAME = 'MARKO_ENVIRONMENT_VARIABLES_TEST';

describe('EnvironmentVariables', function (): void {
    beforeEach(function (): void {
        $this->originalEnv = $_ENV;
        $this->originalGetenv = [];

        foreach ([ENVIRONMENT_VARIABLES_TEST_NAME, 'MARKO_ENV', 'APP_ENV', 'DISCOVERY_CACHE_ENABLED', 'DISCOVERY_CACHE_PATH'] as $name) {
            $this->originalGetenv[$name] = getenv($name);
            unset($_ENV[$name]);
            putenv($name);
        }
    });

    afterEach(function (): void {
        $_ENV = $this->originalEnv;

        foreach ($this->originalGetenv as $name => $value) {
            putenv($value === false ? $name : "$name=$value");
        }

        if (EnvironmentVariables::isRecording()) {
            EnvironmentVariables::stopRecording();
        }
    });

    it('reads $_ENV first, then getenv(), and returns null when the variable is unset', function (): void {
        expect(EnvironmentVariables::read(ENVIRONMENT_VARIABLES_TEST_NAME))->toBeNull();

        putenv(ENVIRONMENT_VARIABLES_TEST_NAME . '=from-getenv');

        expect(EnvironmentVariables::read(ENVIRONMENT_VARIABLES_TEST_NAME))->toBe('from-getenv');

        $_ENV[ENVIRONMENT_VARIABLES_TEST_NAME] = 'from-env';

        expect(EnvironmentVariables::read(ENVIRONMENT_VARIABLES_TEST_NAME))->toBe('from-env');
    })->issue(618);

    it('returns an empty value as an empty string, not as unset', function (): void {
        $_ENV[ENVIRONMENT_VARIABLES_TEST_NAME] = '';

        expect(EnvironmentVariables::read(ENVIRONMENT_VARIABLES_TEST_NAME))->toBe('');
    })->issue(618);

    it('falls back to getenv() when $_ENV holds a non-scalar value', function (): void {
        $_ENV[ENVIRONMENT_VARIABLES_TEST_NAME] = ['not', 'scalar'];
        putenv(ENVIRONMENT_VARIABLES_TEST_NAME . '=from-getenv');

        expect(EnvironmentVariables::read(ENVIRONMENT_VARIABLES_TEST_NAME))->toBe('from-getenv');
    })->issue(618);

    it('records the name of every variable read while recording, once each, in first-read order', function (): void {
        EnvironmentVariables::read('MARKO_READ_BEFORE_RECORDING');
        EnvironmentVariables::startRecording();
        EnvironmentVariables::read(ENVIRONMENT_VARIABLES_TEST_NAME);
        EnvironmentVariables::record('MARKO_RECORDED_DIRECTLY');
        EnvironmentVariables::read(ENVIRONMENT_VARIABLES_TEST_NAME);
        $names = EnvironmentVariables::stopRecording();
        EnvironmentVariables::read('MARKO_READ_AFTER_RECORDING');

        expect($names)->toBe([ENVIRONMENT_VARIABLES_TEST_NAME, 'MARKO_RECORDED_DIRECTLY'])
            ->and(EnvironmentVariables::isRecording())->toBeFalse();
    })->issue(618);

    it('throws when recording is started twice or stopped without being started', function (): void {
        EnvironmentVariables::startRecording();

        expect(fn () => EnvironmentVariables::startRecording())
            ->toThrow(EnvironmentException::class, 'already being recorded');

        EnvironmentVariables::stopRecording();

        expect(fn () => EnvironmentVariables::stopRecording())
            ->toThrow(EnvironmentException::class, 'not being recorded');
    })->issue(618);

    it('hashes the current value of each named variable without exposing it or recording the read', function (): void {
        $_ENV[ENVIRONMENT_VARIABLES_TEST_NAME] = 'secret';
        EnvironmentVariables::startRecording();
        $before = EnvironmentVariables::valueHashes([ENVIRONMENT_VARIABLES_TEST_NAME, 'MARKO_UNSET_VARIABLE']);

        expect(EnvironmentVariables::stopRecording())->toBe([])
            ->and(array_keys($before))->toBe([ENVIRONMENT_VARIABLES_TEST_NAME, 'MARKO_UNSET_VARIABLE'])
            ->and($before[ENVIRONMENT_VARIABLES_TEST_NAME])->not->toContain('secret');

        $_ENV[ENVIRONMENT_VARIABLES_TEST_NAME] = 'rotated';

        expect(EnvironmentVariables::valueHashes([ENVIRONMENT_VARIABLES_TEST_NAME])[ENVIRONMENT_VARIABLES_TEST_NAME])
            ->not->toBe($before[ENVIRONMENT_VARIABLES_TEST_NAME]);
    })->issue(618);

    it('hashes an empty variable differently from an unset one', function (): void {
        $unset = EnvironmentVariables::valueHashes([ENVIRONMENT_VARIABLES_TEST_NAME]);
        $_ENV[ENVIRONMENT_VARIABLES_TEST_NAME] = '';

        expect(EnvironmentVariables::valueHashes([ENVIRONMENT_VARIABLES_TEST_NAME]))->not->toBe($unset);
    })->issue(618);

    it('records the variables AppEnvironment reads from the real environment', function (): void {
        EnvironmentVariables::startRecording();
        new AppEnvironment()->name();

        expect(EnvironmentVariables::stopRecording())->toBe(['MARKO_ENV', 'APP_ENV']);
    })->issue(618);

    it('records only MARKO_ENV when it decides the environment', function (): void {
        $_ENV['MARKO_ENV'] = 'staging';
        EnvironmentVariables::startRecording();
        new AppEnvironment()->name();

        expect(EnvironmentVariables::stopRecording())->toBe(['MARKO_ENV']);
    })->issue(618);

    it('records nothing when AppEnvironment reads injected variables', function (): void {
        EnvironmentVariables::startRecording();
        new AppEnvironment(['APP_ENV' => 'local'])->name();

        expect(EnvironmentVariables::stopRecording())->toBe([]);
    })->issue(618);

    it('records every variable DiscoveryEnvironment reads', function (): void {
        $discoveryEnvironment = new DiscoveryEnvironment();
        EnvironmentVariables::startRecording();
        $discoveryEnvironment->enabled();
        $discoveryEnvironment->environment();
        $discoveryEnvironment->cachePath();

        expect(EnvironmentVariables::stopRecording())
            ->toBe(['DISCOVERY_CACHE_ENABLED', 'MARKO_ENV', 'APP_ENV', 'DISCOVERY_CACHE_PATH']);
    })->issue(618);
});
