<?php

declare(strict_types=1);

use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;
use Marko\Core\Container\Container;
use Marko\Core\Plugin\InterceptorClassGenerator;
use Marko\Core\Plugin\PluginDiscovery;
use Marko\Core\Plugin\PluginInterceptor;
use Marko\Core\Plugin\PluginRegistry;
use Marko\Queue\Job;
use Marko\Queue\Plugin\RetryDeadlinePlugin;
use Marko\Queue\QueueInterface;
use Marko\Queue\RetryUntilInterface;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeQueue;

class RetryDeadlinePluginTestJob extends Job implements RetryUntilInterface
{
    public int $retryUntilCalls = 0;

    public function __construct(
        private readonly FakeClock $clock,
    ) {}

    public function retryUntil(): DateTimeImmutable
    {
        $this->retryUntilCalls++;

        return $this->clock->now()->modify('+6 hours');
    }

    public function handle(): void {}
}

class RetryDeadlinePluginPlainJob extends Job
{
    public function handle(): void {}
}

/**
 * A QueueInterface resolved from a container with RetryDeadlinePlugin registered, as an app resolves it.
 */
function createRetryDeadlineInterceptedQueue(): QueueInterface
{
    $container = new Container();
    $registry = new PluginRegistry();
    $registry->register((new PluginDiscovery())->parsePluginClass(RetryDeadlinePlugin::class));
    $container->setPluginInterceptor(new PluginInterceptor($container, $registry, new InterceptorClassGenerator()));
    $container->bind(QueueInterface::class, FakeQueue::class);

    return $container->get(QueueInterface::class);
}

describe('RetryDeadlinePlugin', function (): void {
    it('is a before plugin on QueueInterface push() and later()', function (): void {
        $reflection = new ReflectionClass(RetryDeadlinePlugin::class);
        $plugin = $reflection->getAttributes(Plugin::class)[0]->newInstance();
        $push = $reflection->getMethod('beforePush')->getAttributes(Before::class)[0]->newInstance();
        $later = $reflection->getMethod('beforeLater')->getAttributes(Before::class)[0]->newInstance();

        expect($plugin->target)->toBe(QueueInterface::class)
            ->and($push->method)->toBe('push')
            ->and($later->method)->toBe('later');
    });

    it('captures the retry deadline once when the job is pushed', function (): void {
        $clock = new FakeClock('2026-10-31 12:00:00');
        $job = new RetryDeadlinePluginTestJob($clock);
        $queue = createRetryDeadlineInterceptedQueue();

        $queue->push($job);

        expect($job->retryDeadline?->format('Y-m-d H:i:s'))->toBe('2026-10-31 18:00:00')
            ->and($job->retryUntilCalls)->toBe(1);
    });

    it('captures the retry deadline when the job is pushed with a delay', function (): void {
        $clock = new FakeClock('2026-10-31 12:00:00');
        $job = new RetryDeadlinePluginTestJob($clock);

        createRetryDeadlineInterceptedQueue()->later(60, $job);

        expect($job->retryDeadline?->format('Y-m-d H:i:s'))->toBe('2026-10-31 18:00:00');
    });

    it('does not move the deadline when the job is pushed again later', function (): void {
        $clock = new FakeClock('2026-10-31 12:00:00');
        $job = new RetryDeadlinePluginTestJob($clock);
        $queue = createRetryDeadlineInterceptedQueue();
        $queue->push($job);

        $clock->travel('+3 hours');
        $queue->push($job);

        expect($job->retryDeadline?->format('Y-m-d H:i:s'))->toBe('2026-10-31 18:00:00')
            ->and($job->retryUntilCalls)->toBe(1);
    });

    it('captures a fresh deadline once the old one is cleared', function (): void {
        $clock = new FakeClock('2026-10-31 12:00:00');
        $job = new RetryDeadlinePluginTestJob($clock);
        $queue = createRetryDeadlineInterceptedQueue();
        $queue->push($job);

        $clock->travel('+1 day');
        $job->clearRetryDeadline();
        $queue->push($job);

        expect($job->retryDeadline?->format('Y-m-d H:i:s'))->toBe('2026-11-01 18:00:00');
    });

    it('leaves a job without RetryUntilInterface alone', function (): void {
        $job = new RetryDeadlinePluginPlainJob();

        createRetryDeadlineInterceptedQueue()->push($job);

        expect($job->retryDeadline)->toBeNull();
    });

    it('passes the push through to the queue', function (): void {
        $plugin = new RetryDeadlinePlugin();

        expect($plugin->beforePush(new RetryDeadlinePluginPlainJob()))->toBeNull()
            ->and($plugin->beforeLater(5, new RetryDeadlinePluginPlainJob()))->toBeNull();
    });
});
