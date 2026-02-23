<?php

declare(strict_types=1);

namespace Marko\Queue\Rabbitmq;

use Marko\Queue\JobInterface;
use Marko\Queue\QueueInterface;
use Marko\Queue\Rabbitmq\Exchange\ExchangeConfig;
use Marko\Queue\Rabbitmq\Exchange\ExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitmqQueue implements QueueInterface
{
    private bool $declared = false;

    /** @var array<string, int> */
    private array $deliveryTags = [];

    public function __construct(
        private RabbitmqConnection $connection,
        private ExchangeConfig $exchangeConfig,
        private string $defaultQueue = 'default',
    ) {}

    public function push(
        JobInterface $job,
        ?string $queue = null,
    ): string {
        $this->declare($queue);

        $id = $this->generateId();
        $job->setId($id);

        $queueName = $queue ?? $this->defaultQueue;

        $message = new AMQPMessage(
            $job->serialize(),
            [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'application_headers' => new AMQPTable(['job_id' => $id]),
            ],
        );

        $this->connection->channel()->basic_publish(
            $message,
            $this->exchangeConfig->name,
            $this->resolveRoutingKey($queueName),
        );

        return $id;
    }

    public function later(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): string {
        return '';
    }

    public function pop(
        ?string $queue = null,
    ): ?JobInterface {
        $this->declare($queue);

        $queueName = $queue ?? $this->defaultQueue;
        $message = $this->connection->channel()->basic_get($queueName);

        if ($message === null) {
            return null;
        }

        /** @var JobInterface $job */
        $job = unserialize($message->getBody());

        $headers = $message->get('application_headers')->getNativeData();
        $jobId = $headers['job_id'];
        $job->setId($jobId);

        $this->deliveryTags[$jobId] = $message->getDeliveryTag();

        return $job;
    }

    public function size(
        ?string $queue = null,
    ): int {
        return 0;
    }

    public function clear(
        ?string $queue = null,
    ): int {
        return 0;
    }

    public function delete(
        string $jobId,
    ): bool {
        if (!isset($this->deliveryTags[$jobId])) {
            return false;
        }

        $this->connection->channel()->basic_ack($this->deliveryTags[$jobId]);
        unset($this->deliveryTags[$jobId]);

        return true;
    }

    public function release(
        string $jobId,
        int $delay = 0,
    ): bool {
        return false;
    }

    private function declare(
        ?string $queue = null,
    ): void {
        if ($this->declared) {
            return;
        }

        $channel = $this->connection->channel();
        $queueName = $queue ?? $this->defaultQueue;

        $channel->exchange_declare(
            $this->exchangeConfig->name,
            $this->exchangeConfig->type->value,
            passive: false,
            durable: $this->exchangeConfig->durable,
            auto_delete: $this->exchangeConfig->autoDelete,
        );

        $channel->queue_declare(
            $queueName,
            passive: false,
            durable: true,
            exclusive: false,
            auto_delete: false,
        );

        $channel->queue_bind(
            $queueName,
            $this->exchangeConfig->name,
            $this->resolveRoutingKey($queueName),
        );

        $this->declared = true;
    }

    private function resolveRoutingKey(
        string $queueName,
    ): string {
        return match ($this->exchangeConfig->type) {
            ExchangeType::Direct => $queueName,
            ExchangeType::Fanout => '',
            ExchangeType::Topic => $queueName,
            ExchangeType::Headers => '',
        };
    }

    private function generateId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
        );
    }
}
