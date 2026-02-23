<?php

declare(strict_types=1);

use Marko\Queue\QueueInterface;
use Marko\Queue\Rabbitmq\Exchange\ExchangeConfig;
use Marko\Queue\Rabbitmq\Exchange\ExchangeType;
use Marko\Queue\Rabbitmq\RabbitmqConnection;
use Marko\Queue\Rabbitmq\RabbitmqQueue;
use Marko\Queue\Rabbitmq\Tests\Fixtures\TestJob;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

function createMockChannel(
    ?AMQPMessage $basicGetReturn = null,
): AMQPChannel {
    return new class ($basicGetReturn) extends AMQPChannel
    {
        /** @var array<int, array<string, mixed>> */
        public array $calls = [];

        public function __construct(
            private ?AMQPMessage $basicGetReturn = null,
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
        ): mixed {
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
            $this->calls[] = [
                'method' => 'queue_declare',
                'queue' => $queue,
                'durable' => $durable,
            ];

            return null;
        }

        public function queue_bind(
            $queue,
            $exchange,
            $routing_key = '',
            $nowait = false,
            $arguments = [],
            $ticket = null,
        ): mixed {
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
        ): mixed {
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
    };
}

function createTestableRabbitmqConnection(
    AMQPChannel $mockChannel,
): RabbitmqConnection {
    $mockAmqpConnection = new class ($mockChannel) extends AbstractConnection
    {
        public function __construct(
            private AMQPChannel $mockChannel,
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
            private AbstractConnection $mockAmqpConnection,
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

    $queue = new RabbitmqQueue($connection, $exchangeConfig);

    expect($queue)->toBeInstanceOf(QueueInterface::class);
});

test('it pushes job to RabbitMQ queue and returns job ID', function (): void {
    $channel = createMockChannel();
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig);
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

    $queue = new RabbitmqQueue($connection, $exchangeConfig);
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

    $queue = new RabbitmqQueue($connection, $exchangeConfig);
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

    // The body should be the serialized job (which can be unserialized back)
    $unserialized = unserialize($body);
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

    $queue = new RabbitmqQueue($connection, $exchangeConfig);
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
    $job = new TestJob('pop test');
    $job->setId('test-job-id');
    $serialized = $job->serialize();

    $amqpMessage = new AMQPMessage(
        $serialized,
        ['application_headers' => new AMQPTable(['job_id' => 'test-job-id'])],
    );
    $amqpMessage->setDeliveryTag(42);

    $channel = createMockChannel(basicGetReturn: $amqpMessage);
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig);
    $popped = $queue->pop();

    expect($popped)->toBeInstanceOf(TestJob::class)
        ->and($popped->id)->toBe('test-job-id')
        ->and($popped->message)->toBe('pop test');
});

test('it tracks delivery tag for popped job', function (): void {
    $job = new TestJob('track test');
    $job->setId('tracked-job-id');
    $serialized = $job->serialize();

    $amqpMessage = new AMQPMessage(
        $serialized,
        ['application_headers' => new AMQPTable(['job_id' => 'tracked-job-id'])],
    );
    $amqpMessage->setDeliveryTag(99);

    $channel = createMockChannel(basicGetReturn: $amqpMessage);
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig);
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

    $queue = new RabbitmqQueue($connection, $exchangeConfig);
    $result = $queue->pop();

    expect($result)->toBeNull();
});

test('it deletes job by acknowledging delivery tag', function (): void {
    $job = new TestJob('delete test');
    $job->setId('delete-job-id');
    $serialized = $job->serialize();

    $amqpMessage = new AMQPMessage(
        $serialized,
        ['application_headers' => new AMQPTable(['job_id' => 'delete-job-id'])],
    );
    $amqpMessage->setDeliveryTag(77);

    $channel = createMockChannel(basicGetReturn: $amqpMessage);
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig);
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

    $queue = new RabbitmqQueue($connection, $exchangeConfig);
    $result = $queue->delete('nonexistent-job-id');

    expect($result)->toBeFalse();

    $ackCalls = array_filter(
        $channel->calls,
        fn (array $call) => $call['method'] === 'basic_ack',
    );

    expect($ackCalls)->toHaveCount(0);
});

test('it declares exchange and queue on first operation', function (): void {
    $channel = createMockChannel();
    $connection = createTestableRabbitmqConnection($channel);
    $exchangeConfig = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    $queue = new RabbitmqQueue($connection, $exchangeConfig);

    // No declarations before any operation
    expect($channel->calls)->toHaveCount(0);

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

    $queue = new RabbitmqQueue($connection, $exchangeConfig);
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
