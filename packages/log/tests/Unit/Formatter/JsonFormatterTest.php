<?php

declare(strict_types=1);

use Marko\Log\Contracts\LogFormatterInterface;
use Marko\Log\Formatter\JsonFormatter;
use Marko\Log\LogLevel;
use Marko\Log\LogRecord;

function jsonRecord(
    array $context = [],
    string $message = 'Hello',
): LogRecord {
    return new LogRecord(
        level: LogLevel::Error,
        message: $message,
        context: $context,
        datetime: new DateTimeImmutable('2026-01-21 10:30:45.123456', new DateTimeZone('UTC')),
        channel: 'app',
    );
}

/**
 * @return array<string, mixed>
 */
function decodeJsonLine(
    string $line,
): array {
    expect($line)->toEndWith("\n")
        ->and(substr_count($line, "\n"))->toBe(1);

    return json_decode($line, true, flags: JSON_THROW_ON_ERROR);
}

it('implements LogFormatterInterface', function (): void {
    expect(new JsonFormatter())->toBeInstanceOf(LogFormatterInterface::class);
});

it('writes one JSON object per line with datetime, level, channel, message and context', function (): void {
    $line = (new JsonFormatter())->format(jsonRecord(['order' => 42]));

    expect(decodeJsonLine($line))->toBe([
        'datetime' => '2026-01-21T10:30:45.123+00:00',
        'level' => 'error',
        'channel' => 'app',
        'message' => 'Hello',
        'context' => ['order' => 42],
    ]);
});

it('writes an empty context as a JSON object', function (): void {
    $line = (new JsonFormatter())->format(jsonRecord());

    expect($line)->toContain('"context":{}');
});

it('keeps a multi-line message on a single output line', function (): void {
    $line = (new JsonFormatter())->format(jsonRecord(message: "first\nsecond\r\nthird"));

    expect(decodeJsonLine($line)['message'])->toBe("first\nsecond\r\nthird");
});

it('interpolates placeholders in the message', function (): void {
    $line = (new JsonFormatter())->format(jsonRecord(['id' => 7], 'Order {id} failed'));

    expect(decodeJsonLine($line)['message'])->toBe('Order 7 failed');
});

it('redacts the default sensitive keys at any depth, including a placeholder', function (): void {
    $line = (new JsonFormatter())->format(jsonRecord(
        ['password' => 'hunter2', 'user' => ['API_KEY' => 'k', 'name' => 'Ann']],
        'Login with {password}',
    ));
    $decoded = decodeJsonLine($line);

    expect($decoded['message'])->toBe('Login with [redacted]')
        ->and($decoded['context'])->toBe([
            'password' => '[redacted]',
            'user' => ['API_KEY' => '[redacted]', 'name' => 'Ann'],
        ])
        ->and($line)->not->toContain('hunter2');
});

it('uses a custom redact key list', function (): void {
    $line = (new JsonFormatter(redactKeys: ['ssn']))->format(jsonRecord(['ssn' => '1', 'password' => 'p']));

    expect(decodeJsonLine($line)['context'])->toBe(['ssn' => '[redacted]', 'password' => 'p']);
});

it('writes an object as its class name, or its string form when it defines __toString', function (): void {
    $stringable = new class ()
    {
        public function __toString(): string
        {
            return 'stringable value';
        }
    };
    $line = (new JsonFormatter())->format(jsonRecord([
        'plain' => new DateTimeZone('UTC'),
        'stringable' => $stringable,
    ]));

    expect(decodeJsonLine($line)['context'])->toBe([
        'plain' => DateTimeZone::class,
        'stringable' => 'stringable value',
    ]);
});

it('writes an exception as class, message, code, file, line and trace', function (): void {
    $exception = new RuntimeException('Boom', 12);
    $line = (new JsonFormatter())->format(jsonRecord(['exception' => $exception]));
    $written = decodeJsonLine($line)['context']['exception'];

    expect($written['class'])->toBe(RuntimeException::class)
        ->and($written['message'])->toBe('Boom')
        ->and($written['code'])->toBe(12)
        ->and($written['file'])->toBe($exception->getFile())
        ->and($written['line'])->toBe($exception->getLine())
        ->and($written['trace'])->toBe($exception->getTraceAsString());
});

it('includes the previous exception chain', function (): void {
    $exception = new RuntimeException('Outer', 0, new LogicException('Inner'));
    $line = (new JsonFormatter())->format(jsonRecord(['exception' => $exception]));
    $written = decodeJsonLine($line)['context']['exception'];

    expect($written['previous']['class'])->toBe(LogicException::class)
        ->and($written['previous']['message'])->toBe('Inner');
});

it('cuts off recursive context at the depth limit instead of failing', function (): void {
    $recursive = ['name' => 'loop'];
    $recursive['self'] = &$recursive;
    $line = (new JsonFormatter(maxDepth: 3))->format(jsonRecord(['data' => $recursive]));
    $context = decodeJsonLine($line)['context'];

    expect($context['data']['name'])->toBe('loop')
        ->and($context['data']['self']['self'])->toBe(JsonFormatter::MAX_DEPTH_REACHED);
});

it('substitutes invalid UTF-8 instead of failing', function (): void {
    $line = (new JsonFormatter())->format(jsonRecord(['bad' => "caf\xE9"], "bad \xFF message"));
    $decoded = decodeJsonLine($line);

    expect($decoded['context']['bad'])->toBe("caf\u{FFFD}")
        ->and($decoded['message'])->toBe("bad \u{FFFD} message");
});

it('writes resources and non-finite floats without failing', function (): void {
    $stream = fopen('php://memory', 'r');
    $line = (new JsonFormatter())->format(jsonRecord(['handle' => $stream, 'ratio' => INF, 'nan' => NAN]));
    fclose($stream);
    $context = decodeJsonLine($line)['context'];

    expect($context['handle'])->toBe('resource (stream)')
        ->and($context['ratio'])->toBe('INF')
        ->and($context['nan'])->toBe('NAN');
});

it('keeps line terminators and controls escaped so a record cannot forge another line', function (): void {
    $line = (new JsonFormatter())->format(jsonRecord(['v' => "a\u{2028}b\x1b[31m"], "x\u{2029}y"));

    expect($line)->not->toContain("\u{2028}")
        ->and($line)->not->toContain("\u{2029}")
        ->and($line)->not->toContain("\x1b")
        ->and(decodeJsonLine($line)['context']['v'])->toBe("a\u{2028}b\x1b[31m");
});
