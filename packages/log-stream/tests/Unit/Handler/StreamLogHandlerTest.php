<?php

declare(strict_types=1);

use Marko\Log\Contracts\LogHandlerInterface;
use Marko\Log\Exceptions\LogWriteException;
use Marko\Log\Formatter\JsonFormatter;
use Marko\Log\Formatter\LineFormatter;
use Marko\Log\Logger;
use Marko\Log\LogLevel;
use Marko\Log\LogRecord;
use Marko\Log\Stream\Handler\StreamLogHandler;
use Marko\Testing\Fake\FakeClock;

function streamRecord(
    string $message = 'Hello',
): LogRecord {
    return new LogRecord(
        level: LogLevel::Info,
        message: $message,
        context: [],
        datetime: new DateTimeImmutable('2026-01-21 09:30:15'),
        channel: 'app',
    );
}

/**
 * @param resource $stream
 */
function streamContents(
    mixed $stream,
): string {
    rewind($stream);

    return (string) stream_get_contents($stream);
}

it('implements LogHandlerInterface', function (): void {
    $handler = new StreamLogHandler(fopen('php://memory', 'w+'), 'php://memory', new LineFormatter());

    expect($handler)->toBeInstanceOf(LogHandlerInterface::class);
});

it('writes the formatted record so it is readable straight after handle()', function (): void {
    $stream = fopen('php://memory', 'w+');
    $handler = new StreamLogHandler($stream, 'php://memory', new LineFormatter());

    $handler->handle(streamRecord());

    expect(streamContents($stream))->toBe("[2026-01-21 09:30:15] app.INFO: Hello\n");
});

it('appends one line per record', function (): void {
    $stream = fopen('php://memory', 'w+');
    $handler = new StreamLogHandler($stream, 'php://memory', new JsonFormatter());

    $handler->handle(streamRecord('one'));
    $handler->handle(streamRecord('two'));
    $lines = explode("\n", rtrim(streamContents($stream)));

    expect($lines)->toHaveCount(2)
        ->and(json_decode($lines[1], true)['message'])->toBe('two');
});

it('turns off write buffering on the stream', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'marko-log-stream-');
    $stream = fopen($path, 'a');
    $handler = new StreamLogHandler($stream, $path, new LineFormatter());

    $handler->handle(streamRecord('unbuffered'));
    $onDisk = file_get_contents($path);

    fclose($stream);
    unlink($path);

    expect($onDisk)->toContain('unbuffered');
});

it('receives every record that passes the threshold behind the shared Logger', function (): void {
    $stream = fopen('php://memory', 'w+');
    $logger = new Logger(
        handlers: [new StreamLogHandler($stream, 'php://memory', new LineFormatter())],
        channel: 'app',
        minimumLevel: LogLevel::Warning,
        clock: new FakeClock('2026-01-21 09:30:15'),
    );

    $logger->info('dropped');
    $logger->error('kept');

    expect(streamContents($stream))->toBe("[2026-01-21 09:30:15] app.ERROR: kept\n");
});

it('throws LogWriteException naming the stream when the write fails', function (): void {
    $stream = fopen('php://memory', 'r');
    $handler = new StreamLogHandler($stream, 'php://stderr', new LineFormatter());

    expect(fn () => $handler->handle(streamRecord()))
        ->toThrow(LogWriteException::class, 'Failed to write to log stream');

    try {
        $handler->handle(streamRecord());
    } catch (LogWriteException $exception) {
        expect($exception->getContext())->toContain('php://stderr');
    }
});

it('throws LogWriteException when the stream has been closed', function (): void {
    $stream = fopen('php://memory', 'w+');
    $handler = new StreamLogHandler($stream, 'php://stdout', new LineFormatter());
    fclose($stream);

    expect(fn () => $handler->handle(streamRecord()))
        ->toThrow(LogWriteException::class, 'Failed to write to log stream');
});

it('throws LogWriteException when constructed without an open stream', function (): void {
    expect(fn () => new StreamLogHandler('php://stderr', 'php://stderr', new LineFormatter()))
        ->toThrow(LogWriteException::class);
});
