<?php

declare(strict_types=1);

namespace Marko\Queue\Rabbitmq;

use DateTimeImmutable;
use Marko\Queue\FailedJob;
use Marko\Queue\FailedJobRepositoryInterface;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitmqFailedJobRepository implements FailedJobRepositoryInterface
{
    private const string QUEUE_NAME = 'failed_jobs';

    public function __construct(
        private RabbitmqConnection $connection,
    ) {}

    public function store(
        FailedJob $failedJob,
    ): void {
        $channel = $this->connection->channel();

        $channel->queue_declare(
            self::QUEUE_NAME,
            passive: false,
            durable: true,
            exclusive: false,
            auto_delete: false,
        );

        $body = json_encode([
            'id' => $failedJob->id,
            'queue' => $failedJob->queue,
            'payload' => $failedJob->payload,
            'exception' => $failedJob->exception,
            'failedAt' => $failedJob->failedAt->format('c'),
        ], JSON_THROW_ON_ERROR);

        $message = new AMQPMessage($body, [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'message_id' => $failedJob->id,
        ]);

        $channel->basic_publish($message, '', self::QUEUE_NAME);
    }

    public function all(): array
    {
        $channel = $this->connection->channel();
        $failedJobs = [];

        while ($message = $channel->basic_get(self::QUEUE_NAME)) {
            $failedJobs[] = $this->deserializeMessage($message);
            $channel->basic_nack($message->getDeliveryTag(), false, true);
        }

        return $failedJobs;
    }

    public function find(
        string $id,
    ): ?FailedJob {
        $channel = $this->connection->channel();
        $found = null;

        while ($message = $channel->basic_get(self::QUEUE_NAME)) {
            if ($message->get('message_id') === $id) {
                $found = $this->deserializeMessage($message);
            }

            $channel->basic_nack($message->getDeliveryTag(), false, true);
        }

        return $found;
    }

    public function delete(
        string $id,
    ): bool {
        $channel = $this->connection->channel();
        $found = false;

        while ($message = $channel->basic_get(self::QUEUE_NAME)) {
            if ($message->get('message_id') === $id) {
                $channel->basic_ack($message->getDeliveryTag());
                $found = true;
            } else {
                $channel->basic_nack($message->getDeliveryTag(), false, true);
            }
        }

        return $found;
    }

    public function clear(): int
    {
        $channel = $this->connection->channel();

        return (int) $channel->queue_purge(self::QUEUE_NAME);
    }

    public function count(): int
    {
        $channel = $this->connection->channel();

        [, $messageCount] = $channel->queue_declare(self::QUEUE_NAME, passive: true);

        return (int) $messageCount;
    }

    private function deserializeMessage(
        AMQPMessage $message,
    ): FailedJob {
        $data = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return new FailedJob(
            id: $data['id'],
            queue: $data['queue'],
            payload: $data['payload'],
            exception: $data['exception'],
            failedAt: new DateTimeImmutable($data['failedAt']),
        );
    }
}
