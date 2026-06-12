<?php

declare(strict_types=1);

namespace Marko\Queue\Rabbitmq;

use DateMalformedStringException;
use DateTimeImmutable;
use Exception;
use JsonException;
use Marko\Queue\FailedJob;
use Marko\Queue\FailedJobRepositoryInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

readonly class RabbitmqFailedJobRepository implements FailedJobRepositoryInterface
{
    private const string QUEUE_NAME = 'failed_jobs';

    public function __construct(
        private RabbitmqConnection $connection,
    ) {}

    /**
     * @throws JsonException|Exception
     */
    public function store(
        FailedJob $failedJob,
    ): void {
        $channel = $this->connection->channel();

        $channel->queue_declare(
            self::QUEUE_NAME,
            durable: true,
            auto_delete: false,
        );

        $body = json_encode([
            'id' => $failedJob->id,
            'queue' => $failedJob->queue,
            'payload' => $failedJob->payload,
            'exception' => $failedJob->exception,
            'failedAt' => $failedJob->failedAt->format('c'),
        ], JSON_THROW_ON_ERROR);

        $this->publishPersistent($channel, $body, $failedJob->id);
    }

    /**
     * @return list<FailedJob>
     *
     * @throws DateMalformedStringException|JsonException|Exception
     */
    public function all(): array
    {
        $channel = $this->connection->channel();
        $drained = $this->drain($channel);
        $failedJobs = [];

        foreach ($drained as $message) {
            $failedJobs[] = $this->deserializeMessage($message);
            $this->republish($channel, $message);
        }

        return $failedJobs;
    }

    /**
     * @throws DateMalformedStringException|JsonException|Exception
     */
    public function find(
        string $id,
    ): ?FailedJob {
        $channel = $this->connection->channel();
        $drained = $this->drain($channel);
        $found = null;

        foreach ($drained as $message) {
            if ($message->get('message_id') === $id) {
                $found = $this->deserializeMessage($message);
            }

            $this->republish($channel, $message);
        }

        return $found;
    }

    /**
     * @throws Exception
     */
    public function delete(
        string $id,
    ): bool {
        $channel = $this->connection->channel();
        $drained = $this->drain($channel);
        $found = false;

        foreach ($drained as $message) {
            if ($message->get('message_id') === $id) {
                $found = true;
            } else {
                $this->republish($channel, $message);
            }
        }

        return $found;
    }

    /**
     * @throws Exception
     */
    public function clear(): int
    {
        $channel = $this->connection->channel();

        return (int) $channel->queue_purge(self::QUEUE_NAME);
    }

    /**
     * @throws Exception
     */
    public function count(): int
    {
        $channel = $this->connection->channel();

        [, $messageCount] = $channel->queue_declare(self::QUEUE_NAME, passive: true);

        return (int) $messageCount;
    }

    /**
     * Drain all messages from the queue with basic_ack, returning them for processing.
     * This avoids infinite nack-requeue loops on the real broker.
     *
     * @return list<AMQPMessage>
     *
     * @throws Exception
     */
    private function drain(AMQPChannel $channel): array
    {
        $messages = [];

        while ($message = $channel->basic_get(self::QUEUE_NAME)) {
            $messages[] = $message;
            $channel->basic_ack($message->getDeliveryTag());
        }

        return $messages;
    }

    /**
     * Re-publish a drained message back to the failed jobs queue (restore it).
     *
     * @throws Exception
     */
    private function republish(
        AMQPChannel $channel,
        AMQPMessage $message,
    ): void {
        $this->publishPersistent($channel, $message->getBody(), $message->get('message_id'));
    }

    /**
     * Publish a persistent message to the failed jobs queue.
     *
     * @throws Exception
     */
    private function publishPersistent(
        AMQPChannel $channel,
        string $body,
        string $messageId,
    ): void {
        $channel->basic_publish(
            new AMQPMessage($body, [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id' => $messageId,
            ]),
            '',
            self::QUEUE_NAME,
        );
    }

    /**
     * @throws DateMalformedStringException|JsonException
     */
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
