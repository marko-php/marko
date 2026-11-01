<?php

declare(strict_types=1);

use Marko\Log\Config\LogConfig;
use Marko\Log\Exceptions\InvalidLogConfigException;
use Marko\Log\Formatter\JsonFormatter;
use Marko\Log\Formatter\LineFormatter;
use Marko\Log\Formatter\LogFormatterFactory;
use Marko\Log\LogLevel;
use Marko\Log\LogRecord;
use Marko\Testing\Fake\FakeConfigRepository;

function formatterFactoryFor(
    string $formatter,
): LogFormatterFactory {
    return new LogFormatterFactory(new LogConfig(new FakeConfigRepository([
        'log.formatter' => $formatter,
        'log.format' => '{level}|{message}|{context}',
        'log.date_format' => 'Y-m-d',
        'log.escape_newlines' => false,
        'log.redact_keys' => ['ssn'],
    ])));
}

function formatterFactoryRecord(): LogRecord
{
    return new LogRecord(
        level: LogLevel::Info,
        message: "a\nb",
        context: ['ssn' => '123-45-6789', 'password' => 'p'],
        datetime: new DateTimeImmutable('2026-01-21 10:30:45'),
        channel: 'app',
    );
}

it('builds a LineFormatter from the line settings and redact keys', function (): void {
    $formatter = formatterFactoryFor('line')->create();

    expect($formatter)->toBeInstanceOf(LineFormatter::class)
        ->and($formatter->format(formatterFactoryRecord()))
        ->toBe("INFO|a\nb|{\"ssn\":\"[redacted]\",\"password\":\"p\"}\n");
});

it('builds a JsonFormatter with the configured redact keys', function (): void {
    $formatter = formatterFactoryFor('json')->create();
    $decoded = json_decode($formatter->format(formatterFactoryRecord()), true);

    expect($formatter)->toBeInstanceOf(JsonFormatter::class)
        ->and($decoded['context'])->toBe(['ssn' => '[redacted]', 'password' => 'p']);
});

it('throws for an unknown formatter', function (): void {
    expect(fn () => formatterFactoryFor('xml')->create())
        ->toThrow(InvalidLogConfigException::class, "Unknown log formatter 'xml'");
});
