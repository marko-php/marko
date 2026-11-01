<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Log\Config\LogConfig;
use Marko\Log\Exceptions\InvalidLogConfigException;
use Marko\Log\LoggerFactory;
use Marko\Log\Tests\Fixtures\RecordingHandler;
use Marko\Log\Tests\Fixtures\SecondRecordingHandler;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;

function loggerFactoryWith(
    Container $container,
    array $config,
): LoggerFactory {
    return new LoggerFactory(
        config: new LogConfig(new FakeConfigRepository([
            'log.channel' => 'app',
            'log.level' => 'info',
            'log.handler_map' => [
                'file' => RecordingHandler::class,
                'stream' => SecondRecordingHandler::class,
            ],
            ...$config,
        ])),
        container: $container,
        clock: new FakeClock('2026-01-21 09:30:15'),
    );
}

it('resolves every configured handler from the container and stacks them in order', function (): void {
    $container = new Container(new PreferenceRegistry());
    $sequence = new ArrayObject();
    $container->instance(RecordingHandler::class, new RecordingHandler('file', $sequence));
    $container->instance(SecondRecordingHandler::class, new SecondRecordingHandler('stream', $sequence));

    $logger = loggerFactoryWith($container, ['log.handlers' => ['stream', 'file']])->create();
    $logger->info('Stacked');

    expect($sequence->getArrayCopy())->toBe(['stream', 'file']);
});

it('passes the configured channel, level and clock to the logger', function (): void {
    $container = new Container(new PreferenceRegistry());
    $handler = new RecordingHandler();
    $container->instance(RecordingHandler::class, $handler);

    $logger = loggerFactoryWith($container, ['log.handlers' => ['file']])->create();
    $logger->debug('Below threshold');
    $logger->info('Kept');

    expect($handler->records)->toHaveCount(1)
        ->and($handler->records[0]->channel)->toBe('app')
        ->and($handler->records[0]->datetime->format('Y-m-d H:i:s'))->toBe('2026-01-21 09:30:15');
});

it('throws for an unknown handler name before resolving anything', function (): void {
    $container = new Container(new PreferenceRegistry());

    expect(fn () => loggerFactoryWith($container, ['log.handlers' => ['syslog']])->create())
        ->toThrow(InvalidLogConfigException::class, "Unknown log handler 'syslog'");
});
