<?php

declare(strict_types=1);

namespace Marko\Queue\Sync\Tests;

use Marko\Core\Container\Container;
use Marko\Core\Event\AsyncObserverDispatcherInterface;
use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcher;
use Marko\Core\Event\ObserverDefinition;
use Marko\Core\Event\ObserverRegistry;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\AsyncObserverJob;
use Marko\Queue\Exceptions\JobFailedException;
use Marko\Queue\FailedJobHandler;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobRunner;
use Marko\Queue\QueueAsyncObserverDispatcher;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;
use Marko\Queue\Sync\NullFailedJobRepository;
use Marko\Queue\Sync\SyncQueue;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeLogger;
use RuntimeException;

class InvoicePaid extends Event
{
    public function __construct(
        public readonly string $invoiceNumber,
    ) {}
}

class RecordInvoicePaid
{
    /** @var list<InvoicePaid> */
    public static array $handled = [];

    /** @noinspection PhpUnused - Invoked via reflection */
    public function handle(
        InvoicePaid $event,
    ): void {
        self::$handled[] = $event;
    }
}

class FailingInvoiceObserver
{
    /** @noinspection PhpUnused - Invoked via reflection */
    public function handle(
        InvoicePaid $event,
    ): never {
        throw new RuntimeException("Could not notify for $event->invoiceNumber");
    }
}

function dispatchThroughSyncQueue(
    string $observerClass,
    InvoicePaid $event,
): void {
    $container = new Container();
    $jobEnvelope = new JobEnvelope(
        new EncryptionConfig(new FakeConfigRepository(['encryption.key' => 'sync-async-key'])),
    );
    $container->instance(JobEnvelope::class, $jobEnvelope);
    $runner = new JobRunner($container, $jobEnvelope, new FakeEventDispatcher());
    $container->instance(QueueInterface::class, new SyncQueue(
        $runner,
        new FailedJobHandler(
            new NullFailedJobRepository(),
            $jobEnvelope,
            $runner,
            new FakeEventDispatcher(),
            new FakeClock(),
            logger: new FakeLogger(),
        ),
        new QueueConfig(new FakeConfigRepository(['queue.queue' => 'default'])),
    ));
    $container->bind(AsyncObserverDispatcherInterface::class, QueueAsyncObserverDispatcher::class);

    $registry = new ObserverRegistry();
    $registry->register(new ObserverDefinition(
        observerClass: $observerClass,
        eventClass: InvoicePaid::class,
        async: true,
    ));

    new EventDispatcher($container, $registry)->dispatch($event);
}

it('runs async observers through the SyncQueue path when queue-sync is installed', function (): void {
    RecordInvoicePaid::$handled = [];
    $event = new InvoicePaid('INV-7');

    dispatchThroughSyncQueue(RecordInvoicePaid::class, $event);

    // The observer gets a copy unwrapped from the signed envelope, not the
    // dispatched instance: proof that it went through the queue path.
    expect(RecordInvoicePaid::$handled)->toHaveCount(1)
        ->and(RecordInvoicePaid::$handled[0])->toEqual($event)
        ->and(RecordInvoicePaid::$handled[0])->not->toBe($event);
});

it('reports a failing async observer as a failed AsyncObserverJob under the sync driver', function (): void {
    expect(fn () => dispatchThroughSyncQueue(FailingInvoiceObserver::class, new InvoicePaid('INV-8')))
        ->toThrow(
            JobFailedException::class,
            "Job '" . AsyncObserverJob::class . "' failed: Could not notify for INV-8",
        );
});
