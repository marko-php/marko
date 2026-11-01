<?php

declare(strict_types=1);

use Marko\Config\Env;
use Marko\Config\Exceptions\ConfigException;
use Marko\Core\Discovery\DiscoveryEnvironment;
use Marko\Core\Environment\EnvironmentVariables;

const ENV_TEST_VARIABLE = 'MARKO_ENV_READER_TEST';

beforeEach(function (): void {
    unset($_ENV[ENV_TEST_VARIABLE]);
    putenv(ENV_TEST_VARIABLE);
});

afterEach(function (): void {
    unset($_ENV[ENV_TEST_VARIABLE]);
    putenv(ENV_TEST_VARIABLE);
});

/**
 * Run a callback and return the ConfigException it throws.
 */
function captureEnvException(
    callable $callback,
): ConfigException {
    try {
        $callback();
    } catch (ConfigException $exception) {
        return $exception;
    }

    throw new RuntimeException('Expected a ConfigException to be thrown');
}

describe('lookup', function (): void {
    it('reads $_ENV first, then getenv()', function (): void {
        putenv(ENV_TEST_VARIABLE . '=from-getenv');

        expect(Env::string(ENV_TEST_VARIABLE, 'default'))->toBe('from-getenv');

        $_ENV[ENV_TEST_VARIABLE] = 'from-env-array';

        expect(Env::string(ENV_TEST_VARIABLE, 'default'))->toBe('from-env-array');
    });

    it('returns the default when the variable is unset', function (): void {
        expect(Env::string(ENV_TEST_VARIABLE, 'default'))->toBe('default')
            ->and(Env::nullableString(ENV_TEST_VARIABLE))->toBeNull()
            ->and(Env::int(ENV_TEST_VARIABLE, 3600))->toBe(3600)
            ->and(Env::nullableInt(ENV_TEST_VARIABLE))->toBeNull()
            ->and(Env::float(ENV_TEST_VARIABLE, 1.5))->toBe(1.5)
            ->and(Env::bool(ENV_TEST_VARIABLE, true))->toBeTrue()
            ->and(Env::list(ENV_TEST_VARIABLE, ['*']))->toBe(['*']);
    });

    it('returns the default when the variable is an empty string', function (): void {
        $_ENV[ENV_TEST_VARIABLE] = '';

        expect(Env::string(ENV_TEST_VARIABLE, 'default'))->toBe('default')
            ->and(Env::nullableString(ENV_TEST_VARIABLE))->toBeNull()
            ->and(Env::int(ENV_TEST_VARIABLE, 3600))->toBe(3600)
            ->and(Env::nullableInt(ENV_TEST_VARIABLE))->toBeNull()
            ->and(Env::float(ENV_TEST_VARIABLE, 1.5))->toBe(1.5)
            ->and(Env::bool(ENV_TEST_VARIABLE, false))->toBeFalse()
            ->and(Env::list(ENV_TEST_VARIABLE, ['a']))->toBe(['a']);
    });

    it('returns the default from typed readers when the value is only whitespace', function (): void {
        $_ENV[ENV_TEST_VARIABLE] = '   ';

        expect(Env::int(ENV_TEST_VARIABLE, 3600))->toBe(3600)
            ->and(Env::nullableInt(ENV_TEST_VARIABLE))->toBeNull()
            ->and(Env::float(ENV_TEST_VARIABLE, 1.5))->toBe(1.5)
            ->and(Env::bool(ENV_TEST_VARIABLE, true))->toBeTrue()
            ->and(Env::list(ENV_TEST_VARIABLE, ['a']))->toBe(['a']);
    });

    it('returns the default when getenv() holds an empty string', function (): void {
        putenv(ENV_TEST_VARIABLE . '=');

        expect(Env::int(ENV_TEST_VARIABLE, 30))->toBe(30);
    });

    it('reads non-string scalars placed in $_ENV', function (): void {
        $_ENV[ENV_TEST_VARIABLE] = 8080;

        expect(Env::int(ENV_TEST_VARIABLE, 80))->toBe(8080);

        $_ENV[ENV_TEST_VARIABLE] = true;

        expect(Env::bool(ENV_TEST_VARIABLE, false))->toBeTrue();

        $_ENV[ENV_TEST_VARIABLE] = false;

        expect(Env::bool(ENV_TEST_VARIABLE, true))->toBeFalse();
    });

    it('throws ConfigException when $_ENV holds an array', function (): void {
        $_ENV[ENV_TEST_VARIABLE] = ['nested'];

        expect(fn (): string => Env::string(ENV_TEST_VARIABLE, 'default'))
            ->toThrow(ConfigException::class, 'Environment variable "' . ENV_TEST_VARIABLE . '" must be a string');
    });
});

describe('string', function (): void {
    it('returns the raw value unchanged', function (): void {
        $_ENV[ENV_TEST_VARIABLE] = ' padded value ';

        expect(Env::string(ENV_TEST_VARIABLE, 'default'))->toBe(' padded value ');
    });

    it('returns the value from nullableString when it is set', function (): void {
        $_ENV[ENV_TEST_VARIABLE] = 'secret';

        expect(Env::nullableString(ENV_TEST_VARIABLE))->toBe('secret');
    });

    it('returns a non-null default from nullableString when unset', function (): void {
        expect(Env::nullableString(ENV_TEST_VARIABLE, 'guest'))->toBe('guest');
    });
});

describe('int', function (): void {
    it('parses integer values', function (string $raw, int $expected): void {
        $_ENV[ENV_TEST_VARIABLE] = $raw;

        expect(Env::int(ENV_TEST_VARIABLE, 1))->toBe($expected);
    })->with([
        'positive' => ['3600', 3600],
        'zero' => ['0', 0],
        'negative' => ['-5', -5],
        'surrounding whitespace' => [' 42 ', 42],
    ]);

    it('rejects values that are not whole numbers', function (string $raw): void {
        $_ENV[ENV_TEST_VARIABLE] = $raw;

        expect(fn (): int => Env::int(ENV_TEST_VARIABLE, 1))
            ->toThrow(ConfigException::class, 'Environment variable "' . ENV_TEST_VARIABLE . '" must be an integer');
    })->with(['abc', '10s', '1.5', '1e3', '1h', 'true']);

    it('enforces min', function (): void {
        $_ENV[ENV_TEST_VARIABLE] = '-1';

        expect(fn (): int => Env::int(ENV_TEST_VARIABLE, 3600, min: 0))
            ->toThrow(ConfigException::class, 'Environment variable "' . ENV_TEST_VARIABLE . '" must be at least 0');
    });

    it('enforces max', function (): void {
        $_ENV[ENV_TEST_VARIABLE] = '70000';

        expect(fn (): int => Env::int(ENV_TEST_VARIABLE, 80, max: 65535))
            ->toThrow(
                ConfigException::class,
                'Environment variable "' . ENV_TEST_VARIABLE . '" must be at most 65535',
            );
    });

    it('accepts values on the min and max boundaries', function (): void {
        $_ENV[ENV_TEST_VARIABLE] = '1';

        expect(Env::int(ENV_TEST_VARIABLE, 80, min: 1, max: 65535))->toBe(1);

        $_ENV[ENV_TEST_VARIABLE] = '65535';

        expect(Env::int(ENV_TEST_VARIABLE, 80, min: 1, max: 65535))->toBe(65535);
    });

    it('parses nullableInt values and enforces its range', function (): void {
        $_ENV[ENV_TEST_VARIABLE] = '100';

        expect(Env::nullableInt(ENV_TEST_VARIABLE, min: 1))->toBe(100);

        $_ENV[ENV_TEST_VARIABLE] = '0';

        expect(fn (): ?int => Env::nullableInt(ENV_TEST_VARIABLE, min: 1))
            ->toThrow(ConfigException::class, 'must be at least 1');

        $_ENV[ENV_TEST_VARIABLE] = 'abc';

        expect(fn (): ?int => Env::nullableInt(ENV_TEST_VARIABLE))
            ->toThrow(ConfigException::class, 'must be an integer');
    });
});

describe('float', function (): void {
    it('parses float values', function (string $raw, float $expected): void {
        $_ENV[ENV_TEST_VARIABLE] = $raw;

        expect(Env::float(ENV_TEST_VARIABLE, 0.0))->toBe($expected);
    })->with([
        'decimal' => ['1.5', 1.5],
        'integer' => ['2', 2.0],
        'exponent' => ['1e3', 1000.0],
        'negative' => ['-0.25', -0.25],
    ]);

    it('rejects values that are not numbers', function (string $raw): void {
        $_ENV[ENV_TEST_VARIABLE] = $raw;

        expect(fn (): float => Env::float(ENV_TEST_VARIABLE, 0.0))
            ->toThrow(ConfigException::class, 'Environment variable "' . ENV_TEST_VARIABLE . '" must be a number');
    })->with(['abc', '10s', '1,5', 'inf', 'nan']);

    it('enforces min and max', function (): void {
        $_ENV[ENV_TEST_VARIABLE] = '-0.1';

        expect(fn (): float => Env::float(ENV_TEST_VARIABLE, 0.5, min: 0.0))
            ->toThrow(ConfigException::class, 'must be at least 0');

        $_ENV[ENV_TEST_VARIABLE] = '1.1';

        expect(fn (): float => Env::float(ENV_TEST_VARIABLE, 0.5, max: 1.0))
            ->toThrow(ConfigException::class, 'must be at most 1');
    });
});

describe('bool', function (): void {
    it('parses true tokens case-insensitively', function (string $raw): void {
        $_ENV[ENV_TEST_VARIABLE] = $raw;

        expect(Env::bool(ENV_TEST_VARIABLE, false))->toBeTrue();
    })->with(['true', 'TRUE', 'True', '1', 'yes', 'YES', 'on', 'On', ' true ']);

    it('parses false tokens case-insensitively', function (string $raw): void {
        $_ENV[ENV_TEST_VARIABLE] = $raw;

        expect(Env::bool(ENV_TEST_VARIABLE, true))->toBeFalse();
    })->with(['false', 'FALSE', 'False', '0', 'no', 'NO', 'off', 'Off', ' off ']);

    it('rejects unrecognised values', function (string $raw): void {
        $_ENV[ENV_TEST_VARIABLE] = $raw;

        expect(fn (): bool => Env::bool(ENV_TEST_VARIABLE, true))
            ->toThrow(ConfigException::class, 'Environment variable "' . ENV_TEST_VARIABLE . '" must be a boolean');
    })->with(['ture', 'enabled', '2', 'y', 'null']);
});

describe('list', function (): void {
    it('splits on commas, trims items and drops empty items', function (): void {
        $_ENV[ENV_TEST_VARIABLE] = ' https://a.test , https://b.test,, ,https://c.test,';

        expect(Env::list(ENV_TEST_VARIABLE, []))->toBe(['https://a.test', 'https://b.test', 'https://c.test']);
    });

    it('returns a single-item list for a value without commas', function (): void {
        $_ENV[ENV_TEST_VARIABLE] = '*';

        expect(Env::list(ENV_TEST_VARIABLE, []))->toBe(['*']);
    });

    it('returns an empty list when the value holds only separators', function (): void {
        $_ENV[ENV_TEST_VARIABLE] = ' , ,';

        expect(Env::list(ENV_TEST_VARIABLE, ['default']))->toBe([]);
    });
});

describe('errors', function (): void {
    it('names the variable, the value and the accepted forms for an integer', function (): void {
        $_ENV[ENV_TEST_VARIABLE] = '10s';

        $exception = captureEnvException(fn (): int => Env::int(ENV_TEST_VARIABLE, 3600));

        expect($exception->getMessage())->toContain(ENV_TEST_VARIABLE)
            ->and($exception->getContext())->toContain('"10s"')
            ->and($exception->getSuggestion())->toContain('whole number')
            ->and($exception->getSuggestion())->toContain('3600');
    });

    it('names the variable, the value and the accepted forms for a boolean', function (): void {
        $_ENV[ENV_TEST_VARIABLE] = 'ture';

        $exception = captureEnvException(fn (): bool => Env::bool(ENV_TEST_VARIABLE, true));

        expect($exception->getMessage())->toContain(ENV_TEST_VARIABLE)
            ->and($exception->getContext())->toContain('"ture"')
            ->and($exception->getSuggestion())->toContain('true, false, 1, 0, yes, no, on, off');
    });

    it('names the allowed range when a value is out of range', function (): void {
        $_ENV[ENV_TEST_VARIABLE] = '-1';

        $exception = captureEnvException(fn (): int => Env::int(ENV_TEST_VARIABLE, 3600, min: 0));

        expect($exception->getContext())->toContain('"-1"')
            ->and($exception->getSuggestion())->toContain('0 or greater');
    });
});

describe('recording reads', function (): void {
    afterEach(function (): void {
        unset($_ENV['MARKO_ENV_RECORD_OTHER']);

        if (Env::isRecording()) {
            Env::stopRecording();
        }
    });

    it('records the name of every variable read through Env while recording, once each', function (): void {
        $_ENV[ENV_TEST_VARIABLE] = '5';

        Env::string('MARKO_ENV_READ_BEFORE_RECORDING', 'x');
        Env::startRecording();
        Env::int(ENV_TEST_VARIABLE, 1);
        Env::string(ENV_TEST_VARIABLE, 'x');
        Env::bool('MARKO_ENV_RECORD_OTHER', false);
        $names = Env::stopRecording();
        Env::string('MARKO_ENV_READ_AFTER_RECORDING', 'x');

        expect($names)->toBe([ENV_TEST_VARIABLE, 'MARKO_ENV_RECORD_OTHER'])
            ->and(Env::isRecording())->toBeFalse();
    })->issue(556);

    it('throws when recording is started twice or stopped without being started', function (): void {
        Env::startRecording();

        expect(fn () => Env::startRecording())->toThrow(ConfigException::class, 'already recording');

        Env::stopRecording();

        expect(fn () => Env::stopRecording())->toThrow(ConfigException::class, 'not recording');
    })->issue(556);

    it('hashes the current value of each named variable', function (): void {
        $_ENV[ENV_TEST_VARIABLE] = 'secret';
        $before = Env::valueHashes([ENV_TEST_VARIABLE, 'MARKO_ENV_RECORD_OTHER']);

        expect(array_keys($before))->toBe([ENV_TEST_VARIABLE, 'MARKO_ENV_RECORD_OTHER'])
            ->and($before[ENV_TEST_VARIABLE])->not->toContain('secret')
            ->and(Env::valueHashes([ENV_TEST_VARIABLE]))->toBe([ENV_TEST_VARIABLE => $before[ENV_TEST_VARIABLE]]);

        $_ENV[ENV_TEST_VARIABLE] = 'rotated';

        expect(Env::valueHashes([ENV_TEST_VARIABLE])[ENV_TEST_VARIABLE])->not->toBe($before[ENV_TEST_VARIABLE]);
    })->issue(556);

    it('hashes an empty variable differently from an unset one, as core readers tell them apart', function (): void {
        $unset = Env::valueHashes([ENV_TEST_VARIABLE]);
        $_ENV[ENV_TEST_VARIABLE] = '';

        expect(Env::valueHashes([ENV_TEST_VARIABLE]))->not->toBe($unset);
    })->issue(618);

    it('also records the variables read through the core environment readers while recording', function (): void {
        Env::startRecording();
        Env::string(ENV_TEST_VARIABLE, 'x');
        new DiscoveryEnvironment()->cachePath();
        $names = Env::stopRecording();

        expect($names)->toBe([ENV_TEST_VARIABLE, 'DISCOVERY_CACHE_PATH'])
            ->and(EnvironmentVariables::isRecording())->toBeFalse();
    })->issue(618);
});
