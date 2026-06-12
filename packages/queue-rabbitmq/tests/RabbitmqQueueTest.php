<?php

declare(strict_types=1);

use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\Exceptions\SerializationException;
use Marko\Queue\JobEnvelope;
use Marko\Queue\QueueInterface;
use Marko\Queue\Rabbitmq\Exchange\ExchangeConfig;
use Marko\Queue\Rabbitmq\Exchange\ExchangeType;
use Marko\Queue\Rabbitmq\RabbitmqConnection;
use Marko\Queue\Rabbitmq\RabbitmqQueue;
use Marko\Queue\Rabbitmq\Tests\Fixtures\TestJob;
use Marko\Testing\Fake\FakeConfigRepository;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

/** @noinspection PhpMissingParentConstructorInspection - Test stub intentionally skips parent */
class MockQueueChannel extends AMQPChannel
{
    /** @var array<int, array<string, mixed>> */
    public array $calls = [];

    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct(
        private readonly ?AMQPMessage $basicGetReturn = null,
        private readonly int $queueMessageCount = 0,
        private readonly int $queuePurgeCount = 0,
        private readonly bool $passiveDeclareThrows = false,
    ) {}

    public function exchange_declare(
        $exchange,
        $type,
        $passive = false,
        $durable = false,
        $auto_delete = true,
        $internal = false,
        $nowait = false,
        $arguments = [],
        $ticket = null,
    ): null {
        $this->calls[] = [
            'method' => 'exchange_declare',
            'exchange' => $exchange,
            'type' => $type,
            'durable' => $durable,
            'auto_delete' => $auto_delete,
        ];

        return null;
    }

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
        if ($passive && $this->passiveDeclareThrows) {
            throw new RuntimeException('NOT_FOUND - no queue');
        }

        $this->calls[] = [
            'method' => 'queue_declare',
            'queue' => $queue,
            'passive' => $passive,
            'durable' => $durable,
            'arguments' => $arguments,
        ];

        return [$queue, $this->queueMessageCount, 0];
    }

    public function queue_bind(
        $queue,
        $exchange,
        $routing_key = '',
        $nowait = false,
        $arguments = [],
        $ticket = null,
    ): null {
        $this->calls[] = [
            'method' => 'queue_bind',
            'queue' => $queue,
            'exchange' => $exchange,
            'routing_key' => $routing_key,
        ];

        return null;
    }

    public function basic_publish(
        $msg,
        $exchange = '',
        $routing_key = '',
        $mandatory = false,
        $immediate = false,
        $ticket = null,
    ): void {
        $this->calls[] = [
            'method' => 'basic_publish',
            'msg' => $msg,
            'exchange' => $exchange,
            'routing_key' => $routing_key,
        ];
    }

    public function basic_get(
        $queue = '',
        $no_ack = false,
        $ticket = null,
    ): ?AMQPMessage {
        $this->calls[] = [
            'method' => 'basic_get',
            'queue' => $queue,
        ];

        return $this->basicGetReturn;
    }

    public function basic_ack(
        $delivery_tag,
        $multiple = false,
    ): void {
        $this->calls[] = [
            'method' => 'basic_ack',
            'delivery_tag' => $delivery_tag,
        ];
    }

    public function basic_nack(
        $delivery_tag,
        $multiple = false,
        $requeue = false,
    ): void {
        $this->calls[] = [
            'method' => 'basic_nack',
            'delivery_tag' => $delivery_tag,
            'multiple' => $multiple,
            'requeue' => $requeue,
        ];
    }

    public function queue_purge(
        $queue = '',
        $nowait = false,
        $ticket = null,
    ): ?int {
        $this->calls[] = [
            'method' => 'queue_purge',
            'queue' => $queue,
        ];

        return $this->queuePurgeCount;
    }
}

function createMockChannel(
    ?AMQPMessage $basicGetReturn = null,
    int $queueMessageCount = 0,
    int $queuePurgeCount = 0,
    bool $passiveDeclareThrows = false,
): MockQueueChannel {
    return new MockQueueChannel($basicGetReturn, $queueMessageCount, $queuePurgeCount, $passiveDeclareThrows);
}

function createRabbitmqTestEnvelope(
    string $key = 'test-hmac-key-for-queue-rabbitmq',
): JobEnvelope {
    return new JobEnvelope(new EncryptionConfig(new FakeConfigRepository(['encryption.key' => $key])));
}

function createTestableRabbitmqConnection(
    MockQueueChannel $mockChannel,
): RabbitmqConnection {
    /** @noinspection PhpMissingParentConstructorInspection - Test stub intentionally skips parent */
    $mockAmqpConnection = new class ($mockChannel) extends AbstractConnection
    {
        /** @noinspection PhpMissingParentConstructorInspection */
        public function __construct(
            private readonly MockQueueChannel $mockChannel,
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

    return new class ($mockAmqpConnection) extends RabbitmqConnection
    {
        public function __construct(
            private readonly AbstractConnection $mockAmqpConnection,
        ) {
            parent::__construct();
        }

        protected function createConnection(): AbstractConnection
        {
            return $this->mockAmqpConnection;
        }
    };
}

test('it implements QueueInterface', function (): void {
    $channel = createMockChannel();
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig, createRabbitmqTestEnvelope());

    expect($queue)->toBeInstanceOf(QueueInterface::class);
});

test('it pushes job to RabbitMQ queue and returns job ID', function (): void {
    $channel = createMockChannel();
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig, createRabbitmqTestEnvelope());
    $job = new TestJob('push test');

    $id = $queue->push($job);

    expect($id)->toBeString()
        ->and($id)->toMatch('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/');

    $publishCalls = array_filter($channel->calls, fn (array $call) => $call['method'] === 'basic_publish');
    expect($publishCalls)->toHaveCount(1);
});

test('it sets job ID on pushed job', function (): void {
    $channel = createMockChannel();
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig, createRabbitmqTestEnvelope());
    $job = new TestJob('id test');

    $id = $queue->push($job);

    expect($job->id)->toBe($id);
});

test('it publishes serialized job payload as message body', function (): void {
    $channel = createMockChannel();
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig, createRabbitmqTestEnvelope());
    $job = new TestJob('payload test');

    $queue->push($job);

    $publishCalls = array_values(array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'basic_publish',
    ));

    expect($publishCalls)->toHaveCount(1);

    /** @var AMQPMessage $msg */
    $msg = $publishCalls[0]['msg'];
    $body = $msg->getBody();

    // The body is an HMAC-signed envelope; verify and unwrap before unserializing
    $envelope = createRabbitmqTestEnvelope();
    $unserialized = unserialize($envelope->verifyAndUnwrap($body));
    expect($unserialized)->toBeInstanceOf(TestJob::class)
        ->and($unserialized->message)->toBe('payload test');
});

test('it stores job ID in message header', function (): void {
    $channel = createMockChannel();
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig, createRabbitmqTestEnvelope());
    $job = new TestJob('header test');

    $id = $queue->push($job);

    $publishCalls = array_values(array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'basic_publish',
    ));

    /** @var AMQPMessage $msg */
    $msg = $publishCalls[0]['msg'];
    $headers = $msg->get('application_headers')->getNativeData();

    expect($headers)->toHaveKey('job_id')
        ->and($headers['job_id'])->toBe($id);
});

test('it pops next available job from queue', function (): void {
    $envelope = createRabbitmqTestEnvelope();
    $job = new TestJob('pop test');
    $job->setId('test-job-id');
    $wrappedPayload = $envelope->wrap($job->serialize());

    $amqpMessage = new AMQPMessage(
        $wrappedPayload,
        ['application_headers' => new AMQPTable(['job_id' => 'test-job-id'])],
    );
    $amqpMessage->setDeliveryTag(42);

    $channel = createMockChannel(basicGetReturn: $amqpMessage);
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig, $envelope);

    /** @var TestJob $popped */
    $popped = $queue->pop();

    expect($popped)->toBeInstanceOf(TestJob::class)
        ->and($popped->id)->toBe('test-job-id')
        ->and($popped->message)->toBe('pop test');
});

test('it tracks delivery tag for popped job', function (): void {
    $envelope = createRabbitmqTestEnvelope();
    $job = new TestJob('track test');
    $job->setId('tracked-job-id');
    $wrappedPayload = $envelope->wrap($job->serialize());

    $amqpMessage = new AMQPMessage(
        $wrappedPayload,
        ['application_headers' => new AMQPTable(['job_id' => 'tracked-job-id'])],
    );
    $amqpMessage->setDeliveryTag(99);

    $channel = createMockChannel(basicGetReturn: $amqpMessage);
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig, $envelope);
    $queue->pop();

    // Verify the delivery tag was tracked by deleting the job (which sends ack)
    $result = $queue->delete('tracked-job-id');
    expect($result)->toBeTrue();

    $ackCalls = array_values(array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'basic_ack',
    ));

    expect($ackCalls)->toHaveCount(1)
        ->and($ackCalls[0]['delivery_tag'])->toBe(99);
});

test('it returns null when queue is empty on pop', function (): void {
    $channel = createMockChannel();
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig, createRabbitmqTestEnvelope());
    $result = $queue->pop();

    expect($result)->toBeNull();
});

test('it deletes job by acknowledging delivery tag', function (): void {
    $envelope = createRabbitmqTestEnvelope();
    $job = new TestJob('delete test');
    $job->setId('delete-job-id');
    $wrappedPayload = $envelope->wrap($job->serialize());

    $amqpMessage = new AMQPMessage(
        $wrappedPayload,
        ['application_headers' => new AMQPTable(['job_id' => 'delete-job-id'])],
    );
    $amqpMessage->setDeliveryTag(77);

    $channel = createMockChannel(basicGetReturn: $amqpMessage);
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig, $envelope);
    $queue->pop();

    $deleted = $queue->delete('delete-job-id');

    expect($deleted)->toBeTrue();

    $ackCalls = array_values(array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'basic_ack',
    ));

    expect($ackCalls)->toHaveCount(1)
        ->and($ackCalls[0]['delivery_tag'])->toBe(77);
});

test('it returns false when deleting unknown job ID', function (): void {
    $channel = createMockChannel();
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig, createRabbitmqTestEnvelope());
    $result = $queue->delete('nonexistent-job-id');

    expect($result)->toBeFalse();

    $ackCalls = array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'basic_ack',
    );

    expect($ackCalls)->toBeEmpty();
});

test('it declares exchange and queue on first operation', function (): void {
    $channel = createMockChannel();
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig, createRabbitmqTestEnvelope());

    // No declarations before any operation
    expect($channel->calls)->toBeEmpty();

    // First push triggers declaration
    $queue->push(new TestJob('first'));

    $exchangeDeclareCalls = array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'exchange_declare',
    );
    $queueDeclareCalls = array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'queue_declare',
    );
    $queueBindCalls = array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'queue_bind',
    );

    expect($exchangeDeclareCalls)->toHaveCount(1)
        ->and($queueDeclareCalls)->toHaveCount(1)
        ->and($queueBindCalls)->toHaveCount(1);

    // Second push should not re-declare
    $queue->push(new TestJob('second'));

    $exchangeDeclareCalls = array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'exchange_declare',
    );
    $queueDeclareCalls = array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'queue_declare',
    );

    expect($exchangeDeclareCalls)->toHaveCount(1)
        ->and($queueDeclareCalls)->toHaveCount(1);
});

test('it uses configured exchange type for declaration', function (): void {
    $channel = createMockChannel();
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'fanout-exchange',
        type: ExchangeType::Fanout,
        durable: false,
        autoDelete: true,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig, createRabbitmqTestEnvelope());
    $queue->push(new TestJob('fanout test'));

    $exchangeDeclareCalls = array_values(array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'exchange_declare',
    ));

    expect($exchangeDeclareCalls)->toHaveCount(1)
        ->and($exchangeDeclareCalls[0]['exchange'])->toBe('fanout-exchange')
        ->and($exchangeDeclareCalls[0]['type'])->toBe('fanout')
        ->and($exchangeDeclareCalls[0]['durable'])->toBeFalse()
        ->and($exchangeDeclareCalls[0]['auto_delete'])->toBeTrue();
});

test('it queues delayed job with TTL expiration header', function (): void {
    $channel = createMockChannel();
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig, createRabbitmqTestEnvelope());
    $job = new TestJob('delayed test');

    $queue->later(30, $job);

    $publishCalls = array_values(array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'basic_publish',
    ));

    expect($publishCalls)->toHaveCount(1);

    /** @var AMQPMessage $msg */
    $msg = $publishCalls[0]['msg'];

    // TTL should be delay in milliseconds as string
    expect($msg->get('expiration'))->toBe('30000')
        ->and($msg->get('delivery_mode'))->toBe(AMQPMessage::DELIVERY_MODE_PERSISTENT);

    // Verify the body is the HMAC-signed envelope wrapping the serialized job
    $envelope = createRabbitmqTestEnvelope();
    $unserialized = unserialize($envelope->verifyAndUnwrap($msg->getBody()));
    expect($unserialized)->toBeInstanceOf(TestJob::class)
        ->and($unserialized->message)->toBe('delayed test');
});

test('it declares delay queue with dead letter exchange configuration', function (): void {
    $channel = createMockChannel();
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig, createRabbitmqTestEnvelope());
    $job = new TestJob('dlx test');

    $queue->later(60, $job);

    // Find the delay queue declaration (not the main queue declaration)
    $queueDeclareCalls = array_values(array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'queue_declare' && $call['queue'] === 'default_delay',
    ));

    expect($queueDeclareCalls)->toHaveCount(1)
        ->and($queueDeclareCalls[0]['durable'])->toBeTrue();

    /** @var AMQPTable $arguments */
    $arguments = $queueDeclareCalls[0]['arguments'];
    $nativeData = $arguments->getNativeData();

    expect($nativeData)->toHaveKey('x-dead-letter-exchange')
        ->and($nativeData['x-dead-letter-exchange'])->toBe('test-exchange')
        ->and($nativeData)->toHaveKey('x-dead-letter-routing-key')
        ->and($nativeData['x-dead-letter-routing-key'])->toBe('default');

    // Verify message was published to delay queue, not the main exchange
    $publishCalls = array_values(array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'basic_publish',
    ));

    expect($publishCalls)->toHaveCount(1)
        ->and($publishCalls[0]['exchange'])->toBe('')
        ->and($publishCalls[0]['routing_key'])->toBe('default_delay');
});

test('it returns job ID for delayed job', function (): void {
    $channel = createMockChannel();
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig, createRabbitmqTestEnvelope());
    $job = new TestJob('id delayed test');

    $id = $queue->later(10, $job);

    expect($id)->toBeString()
        ->and($id)->toMatch('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/')
        ->and($job->id)->toBe($id);

    // Verify the message header also has the job ID
    $publishCalls = array_values(array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'basic_publish',
    ));

    /** @var AMQPMessage $msg */
    $msg = $publishCalls[0]['msg'];
    $headers = $msg->get('application_headers')->getNativeData();

    expect($headers['job_id'])->toBe($id);
});

test('it releases job back to queue immediately when no delay', function (): void {
    $envelope = createRabbitmqTestEnvelope();
    $job = new TestJob('release test');
    $job->setId('release-job-id');
    $wrappedPayload = $envelope->wrap($job->serialize());

    $amqpMessage = new AMQPMessage(
        $wrappedPayload,
        ['application_headers' => new AMQPTable(['job_id' => 'release-job-id'])],
    );
    $amqpMessage->setDeliveryTag(55);

    $channel = createMockChannel(basicGetReturn: $amqpMessage);
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig, $envelope);
    $queue->pop();

    $result = $queue->release('release-job-id');

    expect($result)->toBeTrue();

    // Should ack the original delivery (not nack-requeue) and republish with incremented attempts
    $ackCalls = array_values(array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'basic_ack',
    ));

    expect($ackCalls)->toHaveCount(1)
        ->and($ackCalls[0]['delivery_tag'])->toBe(55);

    $publishCalls = array_values(array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'basic_publish',
    ));

    expect($publishCalls)->toHaveCount(1)
        ->and($publishCalls[0]['exchange'])->toBe('test-exchange')
        ->and($publishCalls[0]['routing_key'])->toBe('default');
});

test('it releases job with delay via delay queue mechanism', function (): void {
    $envelope = createRabbitmqTestEnvelope();
    $job = new TestJob('delay release test');
    $job->setId('delay-release-id');
    $wrappedPayload = $envelope->wrap($job->serialize());

    $amqpMessage = new AMQPMessage(
        $wrappedPayload,
        ['application_headers' => new AMQPTable(['job_id' => 'delay-release-id'])],
    );
    $amqpMessage->setDeliveryTag(66);

    $channel = createMockChannel(basicGetReturn: $amqpMessage);
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig, $envelope);
    $queue->pop();

    $result = $queue->release('delay-release-id', 45);

    expect($result)->toBeTrue();

    // Should ack the original delivery (not nack) and republish with incremented attempts
    $ackCalls = array_values(array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'basic_ack',
    ));

    expect($ackCalls)->toHaveCount(1)
        ->and($ackCalls[0]['delivery_tag'])->toBe(66);

    // Should declare the delay queue with DLX config
    $delayQueueCalls = array_values(array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'queue_declare' && $call['queue'] === 'default_delay',
    ));

    expect($delayQueueCalls)->toHaveCount(1);

    /** @var AMQPTable $arguments */
    $arguments = $delayQueueCalls[0]['arguments'];
    $nativeData = $arguments->getNativeData();

    expect($nativeData['x-dead-letter-exchange'])->toBe('test-exchange')
        ->and($nativeData['x-dead-letter-routing-key'])->toBe('default');

    // Should publish to delay queue with TTL
    $publishCalls = array_values(array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'basic_publish' && $call['routing_key'] === 'default_delay',
    ));

    expect($publishCalls)->toHaveCount(1);

    /** @var AMQPMessage $msg */
    $msg = $publishCalls[0]['msg'];

    expect($msg->get('expiration'))->toBe('45000')
        ->and($msg->get('delivery_mode'))->toBe(AMQPMessage::DELIVERY_MODE_PERSISTENT);

    $headers = $msg->get('application_headers')->getNativeData();
    expect($headers['job_id'])->toBe('delay-release-id');
});

test('it returns false when releasing unknown job ID', function (): void {
    $channel = createMockChannel();
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig, createRabbitmqTestEnvelope());
    $result = $queue->release('nonexistent-job-id');

    expect($result)->toBeFalse();

    $nackCalls = array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'basic_nack',
    );

    expect($nackCalls)->toBeEmpty();
});

test('it returns queue size via passive declare', function (): void {
    $channel = createMockChannel(queueMessageCount: 7);
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig, createRabbitmqTestEnvelope());
    $size = $queue->size();

    expect($size)->toBe(7);

    $passiveCalls = array_values(array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'queue_declare' && $call['passive'] === true,
    ));

    expect($passiveCalls)->toHaveCount(1)
        ->and($passiveCalls[0]['queue'])->toBe('default');
});

test('it returns zero size for empty or non-existent queue', function (): void {
    $channel = createMockChannel(passiveDeclareThrows: true);
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig, createRabbitmqTestEnvelope());
    $size = $queue->size('nonexistent-queue');

    expect($size)->toBe(0);
});

test('it clears all messages from queue via purge', function (): void {
    $channel = createMockChannel(queuePurgeCount: 15);
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig, createRabbitmqTestEnvelope());
    $count = $queue->clear();

    expect($count)->toBe(15);

    $purgeCalls = array_values(array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'queue_purge',
    ));

    expect($purgeCalls)->toHaveCount(1)
        ->and($purgeCalls[0]['queue'])->toBe('default');
});

test('it verifies the envelope before unserializing in RabbitmqQueue (pop/consume)', function (): void {
    $envelope = createRabbitmqTestEnvelope();
    $job = new TestJob('verify envelope test');
    $job->setId('verify-job-id');
    $wrappedPayload = $envelope->wrap($job->serialize());

    $amqpMessage = new AMQPMessage(
        $wrappedPayload,
        ['application_headers' => new AMQPTable(['job_id' => 'verify-job-id'])],
    );
    $amqpMessage->setDeliveryTag(1);

    $channel = createMockChannel(basicGetReturn: $amqpMessage);
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig, $envelope);

    /** @var TestJob $popped */
    $popped = $queue->pop();

    expect($popped)->toBeInstanceOf(TestJob::class)
        ->and($popped->message)->toBe('verify envelope test');
});

test('it rejects a tampered RabbitmqQueue payload before unserializing', function (): void {
    $envelope = createRabbitmqTestEnvelope();

    $fakeHmac = str_repeat('b', 64);
    $tamperedPayload = $fakeHmac . '.O:8:"EvilJob":0:{}';

    $amqpMessage = new AMQPMessage(
        $tamperedPayload,
        ['application_headers' => new AMQPTable(['job_id' => 'tampered-id'])],
    );
    $amqpMessage->setDeliveryTag(1);

    $channel = createMockChannel(basicGetReturn: $amqpMessage);
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig, $envelope);

    expect(fn () => $queue->pop())->toThrow(SerializationException::class);
});

test(
    'it republishes a released job with an incremented attempt count so a subsequent pop() observes attempts greater than the original',
    function (): void {
        $envelope = createRabbitmqTestEnvelope();
        $job = new TestJob('attempt test');
        $job->setId('attempt-job-id');
        $wrappedPayload = $envelope->wrap($job->serialize());

        $amqpMessage = new AMQPMessage(
            $wrappedPayload,
            ['application_headers' => new AMQPTable(['job_id' => 'attempt-job-id'])],
        );
        $amqpMessage->setDeliveryTag(10);

        $channel = createMockChannel(basicGetReturn: $amqpMessage);
        $connection = createTestableRabbitmqConnection($channel);
        $exchangeConfig = new ExchangeConfig(
            name: 'test-exchange',
            type: ExchangeType::Direct,
        );

        $queue = new RabbitmqQueue($connection, $exchangeConfig, $envelope);
        $popped = $queue->pop();

        expect($popped->attempts)->toBe(0);

        // Release with delay=1 to use the republish path
        $queue->release('attempt-job-id', 1);

        // The publish call should contain an incremented attempts count
        $publishCalls = array_values(array_filter(
            $channel->calls,
            fn (array $call) => $call['method'] === 'basic_publish',
        ));

        expect($publishCalls)->toHaveCount(1);

        /** @var AMQPMessage $msg */
        $msg = $publishCalls[0]['msg'];
        $republishedJob = unserialize($envelope->verifyAndUnwrap($msg->getBody()));

        expect($republishedJob->attempts)->toBeGreaterThan(0);
    },
);

test(
    'it terminates retries: after maxAttempts releases the attempt count reaches maxAttempts so the worker stops retrying and the job is eligible for the failed store',
    function (): void {
        $envelope = createRabbitmqTestEnvelope();
        $job = new TestJob('terminate test');
        $job->setId('terminate-job-id');
        $maxAttempts = $job->maxAttempts;

        // Simulate maxAttempts releases
        for ($i = 0; $i < $maxAttempts; $i++) {
            // Reconstruct envelope for each pop cycle
            $wrappedPayload = $envelope->wrap($job->serialize());

            $amqpMessage = new AMQPMessage(
                $wrappedPayload,
                ['application_headers' => new AMQPTable(['job_id' => 'terminate-job-id'])],
            );
            $amqpMessage->setDeliveryTag($i + 1);

            $channel = createMockChannel(basicGetReturn: $amqpMessage);
            $connection = createTestableRabbitmqConnection($channel);
            $exchangeConfig = new ExchangeConfig(
                name: 'test-exchange',
                type: ExchangeType::Direct,
            );

            $queue = new RabbitmqQueue($connection, $exchangeConfig, $envelope);
            $queue->pop();
            $queue->release('terminate-job-id', 10);

            // Get the republished job to use in next iteration
            $publishCalls = array_values(array_filter(
                $channel->calls,
                fn (array $call) => $call['method'] === 'basic_publish',
            ));

            /** @var AMQPMessage $msg */
            $msg = $publishCalls[0]['msg'];
            /** @var TestJob $job */
            $job = unserialize($envelope->verifyAndUnwrap($msg->getBody()));
            $job->setId('terminate-job-id');
        }

        expect($job->attempts)->toBe($maxAttempts);
    },
);

test('it releases a job back to its originating queue, not the hardcoded default queue', function (): void {
    $envelope = createRabbitmqTestEnvelope();
    $job = new TestJob('origin queue test');
    $job->setId('origin-job-id');
    $wrappedPayload = $envelope->wrap($job->serialize());

    $amqpMessage = new AMQPMessage(
        $wrappedPayload,
        ['application_headers' => new AMQPTable(['job_id' => 'origin-job-id'])],
    );
    $amqpMessage->setDeliveryTag(20);

    $channel = createMockChannel(basicGetReturn: $amqpMessage);
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    // Pop from a non-default queue
    $queue = new RabbitmqQueue($connection, $exchangeConfig, $envelope);
    $queue->pop(queue: 'custom-queue');

    // Release with delay=0 (immediate republish path)
    $queue->release('origin-job-id', 0);

    $publishCalls = array_values(array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'basic_publish',
    ));

    expect($publishCalls)->toHaveCount(1)
        ->and($publishCalls[0]['routing_key'])->toBe('custom-queue');
});

test('it derives the delay queue name from the originating queue when releasing with a delay', function (): void {
    $envelope = createRabbitmqTestEnvelope();
    $job = new TestJob('delay origin test');
    $job->setId('delay-origin-id');
    $wrappedPayload = $envelope->wrap($job->serialize());

    $amqpMessage = new AMQPMessage(
        $wrappedPayload,
        ['application_headers' => new AMQPTable(['job_id' => 'delay-origin-id'])],
    );
    $amqpMessage->setDeliveryTag(30);

    $channel = createMockChannel(basicGetReturn: $amqpMessage);
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    // Pop from a non-default queue
    $queue = new RabbitmqQueue($connection, $exchangeConfig, $envelope);
    $queue->pop(queue: 'priority-queue');

    // Release with delay — delay queue should be derived from originating queue
    $queue->release('delay-origin-id', 30);

    $delayQueueDeclareCalls = array_values(array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'queue_declare' && str_ends_with($call['queue'], '_delay'),
    ));

    expect($delayQueueDeclareCalls)->toHaveCount(1)
        ->and($delayQueueDeclareCalls[0]['queue'])->toBe('priority-queue_delay');

    $publishCalls = array_values(array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'basic_publish',
    ));

    expect($publishCalls)->toHaveCount(1)
        ->and($publishCalls[0]['routing_key'])->toBe('priority-queue_delay');
});

test('it declares each distinct queue (a second queue name triggers its own queue_declare)', function (): void {
    $channel = createMockChannel();
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig, createRabbitmqTestEnvelope());

    // Push to first queue
    $queue->push(new TestJob('first'), 'queue-a');

    // Push to second distinct queue
    $queue->push(new TestJob('second'), 'queue-b');

    $queueDeclareCalls = array_values(array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'queue_declare' && !($call['passive'] ?? false),
    ));

    $declaredQueueNames = array_map(fn (array $call) => $call['queue'], $queueDeclareCalls);

    expect($declaredQueueNames)->toContain('queue-a')
        ->and($declaredQueueNames)->toContain('queue-b');

    // Exchange should only be declared once even for two queues
    $exchangeDeclareCalls = array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'exchange_declare',
    );

    expect($exchangeDeclareCalls)->toHaveCount(1);
});

test('it preserves the job id when republishing a released job', function (): void {
    $envelope = createRabbitmqTestEnvelope();
    $job = new TestJob('preserve id test');
    $job->setId('preserve-id-job');
    $wrappedPayload = $envelope->wrap($job->serialize());

    $amqpMessage = new AMQPMessage(
        $wrappedPayload,
        ['application_headers' => new AMQPTable(['job_id' => 'preserve-id-job'])],
    );
    $amqpMessage->setDeliveryTag(40);

    $channel = createMockChannel(basicGetReturn: $amqpMessage);
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig, $envelope);
    $queue->pop();
    $queue->release('preserve-id-job', 5);

    $publishCalls = array_values(array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'basic_publish',
    ));

    expect($publishCalls)->toHaveCount(1);

    /** @var AMQPMessage $msg */
    $msg = $publishCalls[0]['msg'];
    $headers = $msg->get('application_headers')->getNativeData();

    expect($headers['job_id'])->toBe('preserve-id-job');
});
