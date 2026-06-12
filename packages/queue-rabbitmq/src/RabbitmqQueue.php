<?php

declare(strict_types=1);

namespace Marko\Queue\Rabbitmq;

use Exception;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobInterface;
use Marko\Queue\QueueInterface;
use Marko\Queue\Rabbitmq\Exchange\ExchangeConfig;
use Marko\Queue\Rabbitmq\Exchange\ExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Random\RandomException;

class RabbitmqQueue implements QueueInterface
{
    /** @var array<string, true> */
    private array $declaredQueues = [];

    private bool $exchangeDeclared = false;

    /** @var array<string, int> */
    private array $deliveryTags = [];

    /** @var array<string, string> */
    private array $messagePayloads = [];

    /** @var array<string, string> */
    private array $queueNames = [];

    public function __construct(
        private readonly RabbitmqConnection $connection,
        private readonly ExchangeConfig $exchangeConfig,
        private readonly JobEnvelope $jobEnvelope,
        private readonly string $defaultQueue = 'default',
    ) {}

    /**
     * @throws RandomException|Exception
     */
    public function push(
        JobInterface $job,
        ?string $queue = null,
    ): string {
        $this->declare($queue);

        $id = $this->generateId();
        $job->setId($id);

        $queueName = $queue ?? $this->defaultQueue;

        $message = new AMQPMessage(
            $this->jobEnvelope->wrap($job->serialize()),
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

    /**
     * @throws RandomException|Exception
     */
    public function later(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): string {
        $this->declare($queue);

        $queueName = $queue ?? $this->defaultQueue;
        $delayQueue = $queueName . '_delay';

        $channel = $this->connection->channel();
        $channel->queue_declare(
            $delayQueue,
            durable: true,
            auto_delete: false,
            arguments: new AMQPTable([
                'x-dead-letter-exchange' => $this->exchangeConfig->name,
                'x-dead-letter-routing-key' => $queueName,
            ]),
        );

        $id = $this->generateId();
        $job->setId($id);

        $message = new AMQPMessage(
            $this->jobEnvelope->wrap($job->serialize()),
            [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'expiration' => (string) ($delay * 1000),
                'application_headers' => new AMQPTable(['job_id' => $id]),
            ],
        );

        $channel->basic_publish($message, '', $delayQueue);

        return $id;
    }

    /**
     * @throws Exception
     */
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
        $job = unserialize($this->jobEnvelope->verifyAndUnwrap($message->getBody()));

        $headers = $message->get('application_headers')->getNativeData();
        $jobId = $headers['job_id'];
        $job->setId($jobId);

        $this->deliveryTags[$jobId] = $message->getDeliveryTag();
        $this->messagePayloads[$jobId] = $message->getBody();
        $this->queueNames[$jobId] = $queueName;

        return $job;
    }

    /**
     * @throws Exception
     */
    public function size(
        ?string $queue = null,
    ): int {
        $queueName = $queue ?? $this->defaultQueue;
        $channel = $this->connection->channel();

        try {
            [, $messageCount] = $channel->queue_declare($queueName, passive: true);

            return $messageCount;
        } catch (Exception) {
            return 0;
        }
    }

    /**
     * @throws Exception
     */
    public function clear(
        ?string $queue = null,
    ): int {
        $this->declare($queue);

        $queueName = $queue ?? $this->defaultQueue;

        return $this->connection->channel()->queue_purge($queueName);
    }

    /**
     * @throws Exception
     */
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

    /**
     * @throws Exception
     */
    public function release(
        string $jobId,
        int $delay = 0,
    ): bool {
        if (!isset($this->deliveryTags[$jobId])) {
            return false;
        }

        $deliveryTag = $this->deliveryTags[$jobId];
        $channel = $this->connection->channel();
        $queueName = $this->queueNames[$jobId];

        /** @var JobInterface $job */
        $job = unserialize($this->jobEnvelope->verifyAndUnwrap($this->messagePayloads[$jobId]));
        $job->incrementAttempts();
        $updatedBody = $this->jobEnvelope->wrap($job->serialize());

        $channel->basic_ack($deliveryTag);

        if ($delay === 0) {
            $message = new AMQPMessage(
                $updatedBody,
                [
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'application_headers' => new AMQPTable(['job_id' => $jobId]),
                ],
            );

            $channel->basic_publish(
                $message,
                $this->exchangeConfig->name,
                $this->resolveRoutingKey($queueName),
            );
        } else {
            $delayQueue = $queueName . '_delay';

            $channel->queue_declare(
                $delayQueue,
                durable: true,
                auto_delete: false,
                arguments: new AMQPTable([
                    'x-dead-letter-exchange' => $this->exchangeConfig->name,
                    'x-dead-letter-routing-key' => $queueName,
                ]),
            );

            $message = new AMQPMessage(
                $updatedBody,
                [
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'expiration' => (string) ($delay * 1000),
                    'application_headers' => new AMQPTable(['job_id' => $jobId]),
                ],
            );

            $channel->basic_publish($message, '', $delayQueue);
        }

        unset($this->deliveryTags[$jobId]);
        unset($this->messagePayloads[$jobId]);
        unset($this->queueNames[$jobId]);

        return true;
    }

    /**
     * @throws Exception
     */
    private function declare(
        ?string $queue = null,
    ): void {
        $queueName = $queue ?? $this->defaultQueue;

        if (isset($this->declaredQueues[$queueName])) {
            return;
        }

        $channel = $this->connection->channel();

        if (!$this->exchangeDeclared) {
            $channel->exchange_declare(
                $this->exchangeConfig->name,
                $this->exchangeConfig->type->value,
                durable: $this->exchangeConfig->durable,
                auto_delete: $this->exchangeConfig->autoDelete,
            );

            $this->exchangeDeclared = true;
        }

        $channel->queue_declare(
            $queueName,
            durable: true,
            auto_delete: false,
        );

        $channel->queue_bind(
            $queueName,
            $this->exchangeConfig->name,
            $this->resolveRoutingKey($queueName),
        );

        $this->declaredQueues[$queueName] = true;
    }

    private function resolveRoutingKey(
        string $queueName,
    ): string {
        return match ($this->exchangeConfig->type) {
            ExchangeType::Direct, ExchangeType::Topic => $queueName,
            ExchangeType::Fanout, ExchangeType::Headers => '',
        };
    }

    /**
     * @throws RandomException
     */
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
