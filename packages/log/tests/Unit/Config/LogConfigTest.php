<?php

declare(strict_types=1);

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Log\Config\LogConfig;
use Marko\Log\Exceptions\InvalidLogConfigException;
use Marko\Log\Exceptions\InvalidLogLevelException;
use Marko\Log\Exceptions\NoDriverException;
use Marko\Log\Formatter\LineFormatter;
use Marko\Log\LogLevel;
use Marko\Log\Tests\Fixtures\RecordingHandler;
use Marko\Log\Tests\Fixtures\SecondRecordingHandler;
use Marko\Testing\Fake\FakeConfigRepository;

it('reads the configured handlers in order, resolved through the handler map', function (): void {
    $config = new LogConfig(new FakeConfigRepository([
        'log.handlers' => ['stream', 'file'],
        'log.handler_map' => [
            'file' => RecordingHandler::class,
            'stream' => SecondRecordingHandler::class,
        ],
    ]));

    expect($config->handlers())->toBe([
        'stream' => SecondRecordingHandler::class,
        'file' => RecordingHandler::class,
    ]);
});

it('throws when handlers is not configured', function (): void {
    $config = new LogConfig(new FakeConfigRepository([
        'log.handler_map' => ['file' => RecordingHandler::class],
    ]));

    expect(fn () => $config->handlers())
        ->toThrow(ConfigNotFoundException::class);
});

it('throws listing the installed handlers when the handler list is empty', function (): void {
    $config = new LogConfig(new FakeConfigRepository([
        'log.handlers' => [],
        'log.handler_map' => [
            'file' => RecordingHandler::class,
            'stream' => SecondRecordingHandler::class,
        ],
    ]));

    expect(fn () => $config->handlers())
        ->toThrow(InvalidLogConfigException::class, "'log.handlers' is empty");

    try {
        $config->handlers();
    } catch (InvalidLogConfigException $exception) {
        expect($exception->getContext())->toContain('file, stream');
    }
});

it('throws listing the installed handlers for an unknown handler name', function (): void {
    $config = new LogConfig(new FakeConfigRepository([
        'log.handlers' => ['file', 'syslog'],
        'log.handler_map' => [
            'file' => RecordingHandler::class,
            'stream' => SecondRecordingHandler::class,
        ],
    ]));

    expect(fn () => $config->handlers())
        ->toThrow(InvalidLogConfigException::class, "Unknown log handler 'syslog'");

    try {
        $config->handlers();
    } catch (InvalidLogConfigException $exception) {
        expect($exception->getContext())->toContain('file, stream');
    }
});

it('throws when a handler name is listed twice', function (): void {
    $config = new LogConfig(new FakeConfigRepository([
        'log.handlers' => ['file', 'file'],
        'log.handler_map' => ['file' => RecordingHandler::class],
    ]));

    expect(fn () => $config->handlers())
        ->toThrow(InvalidLogConfigException::class, "Log handler 'file' is listed more than once");
});

it('throws when a handlers entry is not a non-empty string', function (): void {
    $config = new LogConfig(new FakeConfigRepository([
        'log.handlers' => [''],
        'log.handler_map' => ['file' => RecordingHandler::class],
    ]));

    expect(fn () => $config->handlers())
        ->toThrow(InvalidLogConfigException::class);
});

it('throws NoDriverException when no handler package is installed', function (): void {
    $config = new LogConfig(new FakeConfigRepository([
        'log.handlers' => ['file'],
    ]));

    expect(fn () => $config->handlers())
        ->toThrow(NoDriverException::class);
});

it('throws when a handler map entry is not a LogHandlerInterface class', function (): void {
    $config = new LogConfig(new FakeConfigRepository([
        'log.handlers' => ['file'],
        'log.handler_map' => ['file' => stdClass::class],
    ]));

    expect(fn () => $config->handlers())
        ->toThrow(InvalidLogConfigException::class, "Log handler 'file' maps to stdClass");
});

it('reads formatter from config without fallback', function (): void {
    $config = new LogConfig(new FakeConfigRepository([
        'log.formatter' => 'json',
    ]));

    expect($config->formatter())->toBe('json');
});

it('throws when formatter is not configured', function (): void {
    $config = new LogConfig(new FakeConfigRepository());

    expect(fn () => $config->formatter())
        ->toThrow(ConfigNotFoundException::class);
});

it('reads path from config without fallback', function (): void {
    $config = new LogConfig(new FakeConfigRepository([
        'log.path' => '/var/log/app',
    ]));

    expect($config->path())->toBe('/var/log/app');
});

it('throws when path is not configured', function (): void {
    $config = new LogConfig(new FakeConfigRepository());

    expect(fn () => $config->path())
        ->toThrow(ConfigNotFoundException::class);
});

it('reads level from config without fallback', function (): void {
    $config = new LogConfig(new FakeConfigRepository([
        'log.level' => 'error',
    ]));

    expect($config->level())->toBe(LogLevel::Error);
});

it('throws when level is not configured', function (): void {
    $config = new LogConfig(new FakeConfigRepository());

    expect(fn () => $config->level())
        ->toThrow(ConfigNotFoundException::class);
});

it('throws InvalidLogLevelException for invalid level', function (): void {
    $config = new LogConfig(new FakeConfigRepository([
        'log.level' => 'invalid',
    ]));

    expect(fn () => $config->level())
        ->toThrow(InvalidLogLevelException::class);
});

it('reads channel from config without fallback', function (): void {
    $config = new LogConfig(new FakeConfigRepository([
        'log.channel' => 'api',
    ]));

    expect($config->channel())->toBe('api');
});

it('throws when channel is not configured', function (): void {
    $config = new LogConfig(new FakeConfigRepository());

    expect(fn () => $config->channel())
        ->toThrow(ConfigNotFoundException::class);
});

it('reads format from config without fallback', function (): void {
    $config = new LogConfig(new FakeConfigRepository([
        'log.format' => '{level}: {message}',
    ]));

    expect($config->format())->toBe('{level}: {message}');
});

it('throws when format is not configured', function (): void {
    $config = new LogConfig(new FakeConfigRepository());

    expect(fn () => $config->format())
        ->toThrow(ConfigNotFoundException::class);
});

it('reads date_format from config without fallback', function (): void {
    $config = new LogConfig(new FakeConfigRepository([
        'log.date_format' => 'd/m/Y H:i:s',
    ]));

    expect($config->dateFormat())->toBe('d/m/Y H:i:s');
});

it('throws when date_format is not configured', function (): void {
    $config = new LogConfig(new FakeConfigRepository());

    expect(fn () => $config->dateFormat())
        ->toThrow(ConfigNotFoundException::class);
});

it('reads max_files from config without fallback', function (): void {
    $config = new LogConfig(new FakeConfigRepository([
        'log.max_files' => 14,
    ]));

    expect($config->maxFiles())->toBe(14);
});

it('throws when max_files is not configured', function (): void {
    $config = new LogConfig(new FakeConfigRepository());

    expect(fn () => $config->maxFiles())
        ->toThrow(ConfigNotFoundException::class);
});

it('reads max_file_size from config without fallback', function (): void {
    $config = new LogConfig(new FakeConfigRepository([
        'log.max_file_size' => 5 * 1024 * 1024,
    ]));

    expect($config->maxFileSize())->toBe(5 * 1024 * 1024);
});

it('throws when max_file_size is not configured', function (): void {
    $config = new LogConfig(new FakeConfigRepository());

    expect(fn () => $config->maxFileSize())
        ->toThrow(ConfigNotFoundException::class);
});

it('config file contains all required keys with defaults', function (): void {
    $configPath = dirname(__DIR__, 3) . '/config/log.php';
    $config = require $configPath;

    expect($config)->toBeArray()
        ->and($config)->not->toHaveKey('driver')
        ->and($config)->not->toHaveKey('handler_map')
        ->and($config['handlers'])->toBe(['file'])
        ->and($config['formatter'])->toBe('line')
        ->and($config)->toHaveKey('path')
        ->and($config)->toHaveKey('level')
        ->and($config)->toHaveKey('channel')
        ->and($config)->toHaveKey('format')
        ->and($config)->toHaveKey('date_format')
        ->and($config)->toHaveKey('max_files')
        ->and($config)->toHaveKey('max_file_size')
        ->and($config)->toHaveKey('escape_newlines')
        ->and($config['escape_newlines'])->toBeTrue()
        ->and($config['redact_keys'])->toBe(LineFormatter::DEFAULT_REDACT_KEYS)
        ->and($config['file_mode'])->toBe(0600)
        ->and($config['dir_mode'])->toBe(0700);
});

it('reads redact_keys from config', function (): void {
    $config = new LogConfig(new FakeConfigRepository([
        'log.redact_keys' => ['password', 'ssn'],
    ]));

    expect($config->redactKeys())->toBe(['password', 'ssn']);
});

it('throws when redact_keys is not configured', function (): void {
    $config = new LogConfig(new FakeConfigRepository());

    expect(fn () => $config->redactKeys())
        ->toThrow(ConfigNotFoundException::class);
});

it('throws InvalidLogConfigException when a redact_keys entry is not a non-empty string', function (): void {
    $config = new LogConfig(new FakeConfigRepository([
        'log.redact_keys' => ['password', 42],
    ]));

    expect(fn () => $config->redactKeys())
        ->toThrow(InvalidLogConfigException::class, 'expected a non-empty string, got int');
});

it('reads file_mode and dir_mode from config', function (): void {
    $config = new LogConfig(new FakeConfigRepository([
        'log.file_mode' => 0640,
        'log.dir_mode' => 0750,
    ]));

    expect($config->fileMode())->toBe(0640)
        ->and($config->dirMode())->toBe(0750);
});

it('throws when file_mode or dir_mode is not configured', function (): void {
    $config = new LogConfig(new FakeConfigRepository());

    expect(fn () => $config->fileMode())->toThrow(ConfigNotFoundException::class)
        ->and(fn () => $config->dirMode())->toThrow(ConfigNotFoundException::class);
});

it('throws InvalidLogConfigException for a permission mode outside 0 to 0777', function (): void {
    $config = new LogConfig(new FakeConfigRepository([
        'log.file_mode' => 0o4755,
        'log.dir_mode' => -1,
    ]));

    expect(fn () => $config->fileMode())->toThrow(InvalidLogConfigException::class, "'log.file_mode'")
        ->and(fn () => $config->dirMode())->toThrow(InvalidLogConfigException::class, "'log.dir_mode'");
});

it('reads escape_newlines from config', function (): void {
    $config = new LogConfig(new FakeConfigRepository([
        'log.escape_newlines' => true,
    ]));

    expect($config->escapeNewlines())->toBeTrue();
});

it('throws when escape_newlines is not configured', function (): void {
    $config = new LogConfig(new FakeConfigRepository());

    expect(fn () => $config->escapeNewlines())
        ->toThrow(ConfigNotFoundException::class);
});

it('uses FakeConfigRepository in LogConfigTest', function (): void {
    $repo = new FakeConfigRepository(['log.channel' => 'app']);
    $config = new LogConfig($repo);

    expect($config->channel())->toBe('app');
});
