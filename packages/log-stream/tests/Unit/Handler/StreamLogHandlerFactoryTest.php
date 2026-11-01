<?php

declare(strict_types=1);

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Log\Exceptions\InvalidLogConfigException;
use Marko\Log\Formatter\LineFormatter;
use Marko\Log\Stream\Handler\StreamLogHandler;
use Marko\Log\Stream\Handler\StreamLogHandlerFactory;
use Marko\Testing\Fake\FakeConfigRepository;

function streamFactoryFor(
    string $target,
): StreamLogHandlerFactory {
    return new StreamLogHandlerFactory(
        config: new FakeConfigRepository(['log.stream.target' => $target]),
        formatter: new LineFormatter(),
    );
}

beforeEach(function (): void {
    $this->rrMode = getenv('RR_MODE');
    putenv('RR_MODE');
});

afterEach(function (): void {
    putenv($this->rrMode === false ? 'RR_MODE' : 'RR_MODE=' . $this->rrMode);
});

it('opens a handler for php://stderr and php://stdout', function (string $target): void {
    expect(streamFactoryFor($target)->create())->toBeInstanceOf(StreamLogHandler::class);
})->with(['php://stderr', 'php://stdout']);

it('throws for any other target, listing the supported ones', function (string $target): void {
    expect(fn () => streamFactoryFor($target)->create())
        ->toThrow(InvalidLogConfigException::class, "Unsupported log stream target '$target'");

    try {
        streamFactoryFor($target)->create();
    } catch (InvalidLogConfigException $exception) {
        expect($exception->getSuggestion())->toContain('php://stderr')
            ->and($exception->getSuggestion())->toContain('php://stdout');
    }
})->with(['php://memory', '/var/log/app.log', 'php://output', '']);

it('throws when the target is php://stdout under RoadRunner', function (): void {
    putenv('RR_MODE=http');

    expect(fn () => streamFactoryFor('php://stdout')->create())
        ->toThrow(InvalidLogConfigException::class, 'php://stdout is not allowed under RoadRunner');
});

it('allows php://stderr under RoadRunner', function (): void {
    putenv('RR_MODE=http');

    expect(streamFactoryFor('php://stderr')->create())->toBeInstanceOf(StreamLogHandler::class);
});

it('throws when the target is not configured', function (): void {
    $factory = new StreamLogHandlerFactory(
        config: new FakeConfigRepository(),
        formatter: new LineFormatter(),
    );

    expect(fn () => $factory->create())->toThrow(ConfigNotFoundException::class);
});
