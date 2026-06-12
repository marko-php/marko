<?php

declare(strict_types=1);

namespace Marko\Queue\Rabbitmq\Tests;

use DateTimeImmutable;
use Marko\Queue\FailedJob;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\Rabbitmq\RabbitmqConnection;
use Marko\Queue\Rabbitmq\RabbitmqFailedJobRepository;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Message\AMQPMessage;

/** @noinspection PhpMissingParentConstructorInspection - Test stub intentionally skips parent */
class MockFailedJobChannel extends AMQPChannel
{
    /** @var list<array{body: string, properties: array<string, mixed>}> */
    public array $publishedMessages = [];

    /** @var list<int> */
    public array $ackedTags = [];

    /** @var list<array{tag: int, multiple: bool, requeue: bool}> */
    public array $nackedTags = [];

    public int $purgeCount = 0;

    public int $passiveDeclareCount = 0;

    /** @var list<array{body: string, message_id: string, delivery_tag: int}> */
    public array $queuedMessages = [];

    private int $getIndex = 0;

    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct() {}

    public function queue_declare(
        $queue = '',
        $passive = false,
        $durable = false,
        $exclusive = false,
        $auto_delete = true,
        $nowait = false,
        $arguments = [],
        $ticket = null,
    ): ?array {
        if ($passive) {
            return [$queue, $this->passiveDeclareCount, 0];
        }

        return [$queue, 0, 0];
    }

    public function basic_publish(
        $msg,
        $exchange = '',
        $routing_key = '',
        $mandatory = false,
        $immediate = false,
        $ticket = null,
    ): void {
        $this->publishedMessages[] = [
            'body' => $msg->getBody(),
            'properties' => $msg->get_properties(),
        ];

        $this->queuedMessages[] = [
            'body' => $msg->getBody(),
            'message_id' => $msg->get('message_id'),
            'delivery_tag' => count($this->queuedMessages) + 1,
        ];
    }

    public function basic_get(
        $queue = '',
        $no_ack = false,
        $ticket = null,
    ): ?AMQPMessage {
        if ($this->getIndex >= count($this->queuedMessages)) {
            $this->getIndex = 0;

            return null;
        }

        $messageData = $this->queuedMessages[$this->getIndex];
        $this->getIndex++;

        $msg = new AMQPMessage(
            $messageData['body'],
            ['message_id' => $messageData['message_id']],
        );
        $msg->setDeliveryTag($messageData['delivery_tag']);

        return $msg;
    }

    public function basic_ack(
        $delivery_tag,
        $multiple = false,
    ): void {
        $this->ackedTags[] = $delivery_tag;
    }

    public function basic_nack(
        $delivery_tag,
        $multiple = false,
        $requeue = false,
    ): void {
        $this->nackedTags[] = [
            'tag' => $delivery_tag,
            'multiple' => $multiple,
            'requeue' => $requeue,
        ];
    }

    public function queue_purge(
        $queue = '',
        $nowait = false,
        $ticket = null,
    ): ?int {
        $count = count($this->queuedMessages);
        $this->queuedMessages = [];
        $this->purgeCount++;

        return $count;
    }
}

function createFailedJobTestConnection(
    MockFailedJobChannel $mockChannel,
): RabbitmqConnection {
    return new class ($mockChannel) extends RabbitmqConnection
    {
        public function __construct(
            private readonly MockFailedJobChannel $mockChannel,
        ) {
            parent::__construct();
        }

        protected function createConnection(): AbstractConnection
        {
            /** @noinspection PhpMissingParentConstructorInspection - Test stub intentionally skips parent */
            return new class ($this->mockChannel) extends AbstractConnection
            {
                /** @noinspection PhpMissingParentConstructorInspection */
                public function __construct(
                    private readonly AMQPChannel $mockChannel,
                ) {}

                public function channel(
                    $channel_id = null,
                ): AMQPChannel {
                    return $this->mockChannel;
                }

                public function isConnected(): bool
                {
                    return true;
                }
            };
        }
    };
}

test('it implements FailedJobRepositoryInterface', function (): void {
    $channel = new MockFailedJobChannel();
    $connection = createFailedJobTestConnection($channel);

    $repository = new RabbitmqFailedJobRepository($connection);

    expect($repository)->toBeInstanceOf(FailedJobRepositoryInterface::class);
});

test('it stores failed job as message in failed jobs queue', function (): void {
    $channel = new MockFailedJobChannel();
    $connection = createFailedJobTestConnection($channel);
    $repository = new RabbitmqFailedJobRepository($connection);

    $failedJob = new FailedJob(
        id: 'failed-123',
        queue: 'default',
        payload: '{"class":"TestJob","data":{}}',
        exception: 'RuntimeException: Test error',
        failedAt: new DateTimeImmutable('2024-01-15T10:30:00+00:00'),
    );

    $repository->store($failedJob);

    expect($channel->publishedMessages)->toHaveCount(1);

    $body = json_decode($channel->publishedMessages[0]['body'], true);

    expect($body)->toHaveKey('id', 'failed-123')
        ->and($body)->toHaveKey('queue', 'default')
        ->and($body)->toHaveKey('payload', '{"class":"TestJob","data":{}}')
        ->and($body)->toHaveKey('exception', 'RuntimeException: Test error')
        ->and($body)->toHaveKey('failedAt', '2024-01-15T10:30:00+00:00');
});

test('it stores job ID as message ID property', function (): void {
    $channel = new MockFailedJobChannel();
    $connection = createFailedJobTestConnection($channel);
    $repository = new RabbitmqFailedJobRepository($connection);

    $failedJob = new FailedJob(
        id: 'failed-456',
        queue: 'emails',
        payload: '{"class":"SendEmail"}',
        exception: 'RuntimeException: SMTP error',
        failedAt: new DateTimeImmutable('2024-01-15T11:00:00+00:00'),
    );

    $repository->store($failedJob);

    $properties = $channel->publishedMessages[0]['properties'];

    expect($properties)->toHaveKey('message_id', 'failed-456')
        ->and($properties)->toHaveKey('delivery_mode', AMQPMessage::DELIVERY_MODE_PERSISTENT);
});

test('it retrieves all failed jobs from queue', function (): void {
    $channel = new MockFailedJobChannel();
    $connection = createFailedJobTestConnection($channel);
    $repository = new RabbitmqFailedJobRepository($connection);

    $job1 = new FailedJob(
        id: 'failed-1',
        queue: 'default',
        payload: '{"class":"Job1"}',
        exception: 'Error 1',
        failedAt: new DateTimeImmutable('2024-01-15T10:30:00+00:00'),
    );
    $job2 = new FailedJob(
        id: 'failed-2',
        queue: 'emails',
        payload: '{"class":"Job2"}',
        exception: 'Error 2',
        failedAt: new DateTimeImmutable('2024-01-15T11:00:00+00:00'),
    );

    $repository->store($job1);
    $repository->store($job2);

    $failedJobs = $repository->all();

    expect($failedJobs)->toHaveCount(2)
        ->and($failedJobs[0])->toBeInstanceOf(FailedJob::class)
        ->and($failedJobs[0]->id)->toBe('failed-1')
        ->and($failedJobs[0]->queue)->toBe('default')
        ->and($failedJobs[1]->id)->toBe('failed-2')
        ->and($failedJobs[1]->queue)->toBe('emails');
});

test('it returns empty array when no failed jobs exist', function (): void {
    $channel = new MockFailedJobChannel();
    $connection = createFailedJobTestConnection($channel);
    $repository = new RabbitmqFailedJobRepository($connection);

    $failedJobs = $repository->all();

    expect($failedJobs)->toBeEmpty();
});

test('it finds failed job by ID', function (): void {
    $channel = new MockFailedJobChannel();
    $connection = createFailedJobTestConnection($channel);
    $repository = new RabbitmqFailedJobRepository($connection);

    $job1 = new FailedJob(
        id: 'failed-1',
        queue: 'default',
        payload: '{"class":"Job1"}',
        exception: 'Error 1',
        failedAt: new DateTimeImmutable('2024-01-15T10:30:00+00:00'),
    );
    $job2 = new FailedJob(
        id: 'failed-2',
        queue: 'emails',
        payload: '{"class":"Job2"}',
        exception: 'Error 2',
        failedAt: new DateTimeImmutable('2024-01-15T11:00:00+00:00'),
    );

    $repository->store($job1);
    $repository->store($job2);

    $found = $repository->find('failed-2');

    expect($found)->toBeInstanceOf(FailedJob::class)
        ->and($found->id)->toBe('failed-2')
        ->and($found->queue)->toBe('emails')
        ->and($found->payload)->toBe('{"class":"Job2"}')
        ->and($found->exception)->toBe('Error 2');
});

test('it returns null when failed job not found', function (): void {
    $channel = new MockFailedJobChannel();
    $connection = createFailedJobTestConnection($channel);
    $repository = new RabbitmqFailedJobRepository($connection);

    $job = new FailedJob(
        id: 'failed-1',
        queue: 'default',
        payload: '{"class":"Job1"}',
        exception: 'Error 1',
        failedAt: new DateTimeImmutable('2024-01-15T10:30:00+00:00'),
    );

    $repository->store($job);

    $found = $repository->find('non-existent');

    expect($found)->toBeNull();
});

test('it deletes failed job by ID and returns true', function (): void {
    $channel = new MockFailedJobChannel();
    $connection = createFailedJobTestConnection($channel);
    $repository = new RabbitmqFailedJobRepository($connection);

    $job1 = new FailedJob(
        id: 'failed-1',
        queue: 'default',
        payload: '{"class":"Job1"}',
        exception: 'Error 1',
        failedAt: new DateTimeImmutable('2024-01-15T10:30:00+00:00'),
    );
    $job2 = new FailedJob(
        id: 'failed-2',
        queue: 'emails',
        payload: '{"class":"Job2"}',
        exception: 'Error 2',
        failedAt: new DateTimeImmutable('2024-01-15T11:00:00+00:00'),
    );

    $repository->store($job1);
    $repository->store($job2);

    $result = $repository->delete('failed-1');

    // Drain-then-restore: all messages acked, then non-deleted ones re-published
    expect($result)->toBeTrue()
        ->and($channel->ackedTags)->toHaveCount(2);
});

test('it returns false when deleting non-existent failed job', function (): void {
    $channel = new MockFailedJobChannel();
    $connection = createFailedJobTestConnection($channel);
    $repository = new RabbitmqFailedJobRepository($connection);

    $job = new FailedJob(
        id: 'failed-1',
        queue: 'default',
        payload: '{"class":"Job1"}',
        exception: 'Error 1',
        failedAt: new DateTimeImmutable('2024-01-15T10:30:00+00:00'),
    );

    $repository->store($job);

    $result = $repository->delete('non-existent');

    // Non-matching target: all messages acked and re-published (none deleted)
    expect($result)->toBeFalse()
        ->and($channel->ackedTags)->toHaveCount(1);
});

test('it clears all failed jobs via queue purge', function (): void {
    $channel = new MockFailedJobChannel();
    $connection = createFailedJobTestConnection($channel);
    $repository = new RabbitmqFailedJobRepository($connection);

    $job1 = new FailedJob(
        id: 'failed-1',
        queue: 'default',
        payload: '{"class":"Job1"}',
        exception: 'Error 1',
        failedAt: new DateTimeImmutable('2024-01-15T10:30:00+00:00'),
    );
    $job2 = new FailedJob(
        id: 'failed-2',
        queue: 'emails',
        payload: '{"class":"Job2"}',
        exception: 'Error 2',
        failedAt: new DateTimeImmutable('2024-01-15T11:00:00+00:00'),
    );

    $repository->store($job1);
    $repository->store($job2);

    $count = $repository->clear();

    expect($count)->toBe(2)
        ->and($channel->purgeCount)->toBe(1);
});

test('it counts failed jobs via passive queue declare', function (): void {
    $channel = new MockFailedJobChannel();
    $channel->passiveDeclareCount = 5;
    $connection = createFailedJobTestConnection($channel);
    $repository = new RabbitmqFailedJobRepository($connection);

    $count = $repository->count();

    expect($count)->toBe(5);
});

it('sets the AMQP message_id to the failed job id when storing a failed job', function (): void {
    $channel = new MockFailedJobChannel();
    $connection = createFailedJobTestConnection($channel);
    $repository = new RabbitmqFailedJobRepository($connection);

    $failedJob = new FailedJob(
        id: 'job-uuid-001',
        queue: 'default',
        payload: '{"class":"TestJob"}',
        exception: 'RuntimeException: fail',
        failedAt: new DateTimeImmutable('2024-03-01T12:00:00+00:00'),
    );

    $repository->store($failedJob);

    $properties = $channel->publishedMessages[0]['properties'];

    expect($properties['message_id'])->toBe('job-uuid-001');
});

it('deletes only the matching failed job and returns true, leaving the others intact', function (): void {
    $channel = new MockFailedJobChannel();
    $connection = createFailedJobTestConnection($channel);
    $repository = new RabbitmqFailedJobRepository($connection);

    $job1 = new FailedJob(
        id: 'del-keep-1',
        queue: 'default',
        payload: '{"class":"Job1"}',
        exception: 'Error 1',
        failedAt: new DateTimeImmutable('2024-01-15T10:30:00+00:00'),
    );
    $job2 = new FailedJob(
        id: 'del-target-2',
        queue: 'emails',
        payload: '{"class":"Job2"}',
        exception: 'Error 2',
        failedAt: new DateTimeImmutable('2024-01-15T11:00:00+00:00'),
    );

    $repository->store($job1);
    $repository->store($job2);

    $result = $repository->delete('del-target-2');

    // Returns true for found target
    expect($result)->toBeTrue()
        // Both messages drained and acked
        ->and($channel->ackedTags)->toHaveCount(2)
        ->and($channel->nackedTags)->toBeEmpty()
        // Only the non-matching job is re-published (target is deleted)
        ->and($channel->publishedMessages)->toHaveCount(3); // 2 store + 1 restore
});

it('returns false from delete() when no failed job matches the id', function (): void {
    $channel = new MockFailedJobChannel();
    $connection = createFailedJobTestConnection($channel);
    $repository = new RabbitmqFailedJobRepository($connection);

    $job = new FailedJob(
        id: 'del-false-1',
        queue: 'default',
        payload: '{"class":"Job1"}',
        exception: 'Error 1',
        failedAt: new DateTimeImmutable('2024-01-15T10:30:00+00:00'),
    );

    $repository->store($job);

    $result = $repository->delete('no-such-id');

    expect($result)->toBeFalse();
});

it('returns null from find() when no failed job matches the id', function (): void {
    $channel = new MockFailedJobChannel();
    $connection = createFailedJobTestConnection($channel);
    $repository = new RabbitmqFailedJobRepository($connection);

    $job = new FailedJob(
        id: 'find-null-1',
        queue: 'default',
        payload: '{"class":"Job1"}',
        exception: 'Error 1',
        failedAt: new DateTimeImmutable('2024-01-15T10:30:00+00:00'),
    );

    $repository->store($job);

    $found = $repository->find('does-not-exist');

    expect($found)->toBeNull();
});

it('returns the matching failed job from find() by id and terminates', function (): void {
    $channel = new MockFailedJobChannel();
    $connection = createFailedJobTestConnection($channel);
    $repository = new RabbitmqFailedJobRepository($connection);

    $job1 = new FailedJob(
        id: 'find-term-1',
        queue: 'default',
        payload: '{"class":"Job1"}',
        exception: 'Error 1',
        failedAt: new DateTimeImmutable('2024-01-15T10:30:00+00:00'),
    );
    $job2 = new FailedJob(
        id: 'find-term-2',
        queue: 'emails',
        payload: '{"class":"Job2"}',
        exception: 'Error 2',
        failedAt: new DateTimeImmutable('2024-01-15T11:00:00+00:00'),
    );

    $repository->store($job1);
    $repository->store($job2);

    $found = $repository->find('find-term-2');

    // Returns correct job
    expect($found)->toBeInstanceOf(FailedJob::class)
        ->and($found->id)->toBe('find-term-2')
        // Drain-then-restore: all messages acked (not nacked-requeued)
        ->and($channel->ackedTags)->toHaveCount(2)
        ->and($channel->nackedTags)->toBeEmpty()
        // Restore: both messages re-published (find is read-only)
        ->and($channel->publishedMessages)->toHaveCount(4); // 2 store + 2 restore
});

it(
    'returns all stored failed jobs from all() and the call terminates (no infinite basic_get/basic_nack requeue loop)',
    function (): void {
        $channel = new MockFailedJobChannel();
        $connection = createFailedJobTestConnection($channel);
        $repository = new RabbitmqFailedJobRepository($connection);

        $job1 = new FailedJob(
            id: 'term-1',
            queue: 'default',
            payload: '{"class":"Job1"}',
            exception: 'Error 1',
            failedAt: new DateTimeImmutable('2024-01-15T10:30:00+00:00'),
        );
        $job2 = new FailedJob(
            id: 'term-2',
            queue: 'emails',
            payload: '{"class":"Job2"}',
            exception: 'Error 2',
            failedAt: new DateTimeImmutable('2024-01-15T11:00:00+00:00'),
        );

        $repository->store($job1);
        $repository->store($job2);

        $failedJobs = $repository->all();

        // Returns correct jobs
        expect($failedJobs)->toHaveCount(2)
                ->and($failedJobs[0]->id)->toBe('term-1')
                ->and($failedJobs[1]->id)->toBe('term-2')
                // Drain-then-restore: all messages must be acked (consumed), never nacked-requeued
            ->and($channel->ackedTags)->toHaveCount(2)
                ->and($channel->nackedTags)->toBeEmpty()
                // Restore: messages re-published so they are not lost
            ->and($channel->publishedMessages)->toHaveCount(4); // 2 store + 2 restore
    },
);
