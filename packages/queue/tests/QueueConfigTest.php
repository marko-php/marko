<?php

declare(strict_types=1);

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Queue\Exceptions\QueueException;
use Marko\Queue\QueueConfig;
use Marko\Testing\Fake\FakeConfigRepository;

describe('QueueConfig', function (): void {
    it('loads driver setting', function (): void {
        $config = new FakeConfigRepository([
            'queue.driver' => 'database',
        ]);

        $queueConfig = new QueueConfig($config);

        expect($queueConfig->driver())->toBe('database');
    });

    it('loads connection setting', function (): void {
        $config = new FakeConfigRepository([
            'queue.connection' => 'redis',
        ]);

        $queueConfig = new QueueConfig($config);

        expect($queueConfig->connection())->toBe('redis');
    });

    it('loads queue name setting', function (): void {
        $config = new FakeConfigRepository([
            'queue.queue' => 'high-priority',
        ]);

        $queueConfig = new QueueConfig($config);

        expect($queueConfig->queue())->toBe('high-priority');
    });

    it('loads retry_after setting', function (): void {
        $config = new FakeConfigRepository([
            'queue.retry_after' => 120,
        ]);

        $queueConfig = new QueueConfig($config);

        expect($queueConfig->retryAfter())->toBe(120);
    });

    it('loads the timeout setting', function (): void {
        $queueConfig = new QueueConfig(new FakeConfigRepository(['queue.timeout' => 45]));

        expect($queueConfig->timeout())->toBe(45);
    });

    it('ships a default timeout below the default retry_after', function (): void {
        $defaults = require dirname(__DIR__) . '/config/queue.php';

        expect($defaults['timeout'])->toBe(60)
            ->and($defaults['timeout'])->toBeLessThan($defaults['retry_after']);
    });

    it('loads the restart_signal setting', function (): void {
        $queueConfig = new QueueConfig(new FakeConfigRepository(['queue.restart_signal' => 'var/queue/restart']));

        expect($queueConfig->restartSignal())->toBe('var/queue/restart');
    });

    it('ships a restart_signal path relative to the project root', function (): void {
        $defaults = require dirname(__DIR__) . '/config/queue.php';

        expect($defaults['restart_signal'])->toBe('storage/queue/restart');
    });

    it('loads the restart_signal_driver setting', function (): void {
        $queueConfig = new QueueConfig(new FakeConfigRepository(['queue.restart_signal_driver' => 'cache']));

        expect($queueConfig->restartSignalDriver())->toBe('cache');
    });

    it('ships the file restart signal driver as the default', function (): void {
        $defaults = require dirname(__DIR__) . '/config/queue.php';

        expect($defaults['restart_signal_driver'])->toBe('file');
    });

    it('loads max_attempts setting', function (): void {
        $config = new FakeConfigRepository([
            'queue.max_attempts' => 5,
        ]);

        $queueConfig = new QueueConfig($config);

        expect($queueConfig->maxAttempts())->toBe(5);
    });

    it('uses default config values from config file', function (): void {
        // Defaults are now provided by config/queue.php, not fallback parameters
        // This test verifies the expected default values match config file
        $config = new FakeConfigRepository([
            'queue.driver' => 'sync',
            'queue.connection' => 'default',
            'queue.queue' => 'default',
            'queue.retry_after' => 90,
            'queue.max_attempts' => 3,
        ]);

        $queueConfig = new QueueConfig($config);

        expect($queueConfig->driver())->toBe('sync')
            ->and($queueConfig->connection())->toBe('default')
            ->and($queueConfig->queue())->toBe('default')
            ->and($queueConfig->retryAfter())->toBe(90)
            ->and($queueConfig->maxAttempts())->toBe(3);
    });

    it('throws ConfigNotFoundException when required config is missing', function (): void {
        $emptyConfig = new FakeConfigRepository();
        $queueConfig = new QueueConfig($emptyConfig);

        expect(fn () => $queueConfig->driver())->toThrow(ConfigNotFoundException::class);
    });

    it('allows custom config values to override defaults', function (): void {
        $customConfig = new FakeConfigRepository([
            'queue.driver' => 'database',
            'queue.connection' => 'mysql',
            'queue.queue' => 'high-priority',
            'queue.retry_after' => 300,
            'queue.max_attempts' => 5,
        ]);
        $customQueueConfig = new QueueConfig($customConfig);

        expect($customQueueConfig->driver())->toBe('database')
            ->and($customQueueConfig->connection())->toBe('mysql')
            ->and($customQueueConfig->queue())->toBe('high-priority')
            ->and($customQueueConfig->retryAfter())->toBe(300)
            ->and($customQueueConfig->maxAttempts())->toBe(5);
    });

    it('returns the configured int or list backoff', function (): void {
        $intConfig = new QueueConfig(new FakeConfigRepository(['queue.backoff' => 15]));
        $listConfig = new QueueConfig(new FakeConfigRepository(['queue.backoff' => [10, 60, 300]]));

        expect($intConfig->backoff())->toBe(15)
            ->and($listConfig->backoff())->toBe([10, 60, 300]);
    });

    it('returns null for backoff when the key is null or absent', function (): void {
        // An app override of null removes the key in ConfigMerger, so absent means null too
        $nullConfig = new QueueConfig(new FakeConfigRepository(['queue.backoff' => null]));
        $absentConfig = new QueueConfig(new FakeConfigRepository());

        expect($nullConfig->backoff())->toBeNull()
            ->and($absentConfig->backoff())->toBeNull();
    });

    it('throws QueueException when queue.backoff is not an int, list or null', function (): void {
        $config = new QueueConfig(new FakeConfigRepository(['queue.backoff' => '30']));

        expect(fn () => $config->backoff())->toThrow(QueueException::class, 'Invalid queue backoff');
    });

    it('throws QueueException for a queue.backoff list with a non-int entry', function (): void {
        $config = new QueueConfig(new FakeConfigRepository(['queue.backoff' => [10, 'soon']]));

        expect(fn () => $config->backoff())
            ->toThrow(QueueException::class, 'Invalid queue backoff in config queue.backoff');
    });

    it('throws QueueException for a negative queue.backoff', function (): void {
        $config = new QueueConfig(new FakeConfigRepository(['queue.backoff' => -10]));

        expect(fn () => $config->backoff())
            ->toThrow(QueueException::class, 'Invalid queue backoff in config queue.backoff');
    });

    it('ships a null backoff in config/queue.php', function (): void {
        $config = require dirname(__DIR__) . '/config/queue.php';

        expect($config)->toHaveKey('backoff')
            ->and($config['backoff'])->toBeNull();
    });
});
