<?php

declare(strict_types=1);

namespace Marko\Queue\Rabbitmq;

use Exception;
use Marko\Queue\Exceptions\SerializationException;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobInterface;
use Marko\Queue\PushResult;
use Marko\Queue\QueueInterface;
use Marko\Queue\Rabbitmq\Exceptions\RabbitmqException;
use Marko\Queue\Rabbitmq\Exchange\ExchangeConfig;
use Marko\Queue\Rabbitmq\Exchange\ExchangeType;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Random\RandomException;

class RabbitmqQueue implements QueueInterface
{
    /**
     * Seconds release() waits for the broker to confirm a republished job before giving up.
     */
    private const int CONFIRM_TIMEOUT_SECONDS = 10;

    /** @var array<string, true> */
    private array $declaredQueues = [];

    private bool $exchangeDeclared = false;

    /** @var array<string, int> */
    private array $deliveryTags = [];

    /** @var array<string, string> */
    private array $queueNames = [];

    private bool $confirmsEnabled = false;

    private bool $publishNacked = false;

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
    ): PushResult {
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

        return PushResult::pushed($id);
    }

    /**
     * @throws RandomException|Exception
     */
    public function later(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
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

        return PushResult::pushed($id);
    }

    /**
     * Pop the next job, rejecting any message that cannot be turned into one.
     *
     * A message with a bad signature (tampering, or an APP_KEY rotation with jobs still queued),
     * a missing job_id header, or a payload that is not a job is rejected without requeue, so the
     * broker drops it or routes it to the queue's dead-letter exchange, and the next message is
     * tried. Requeueing it would hand the same poison message to every worker forever.
     *
     * @throws Exception
     */
    public function pop(
        ?string $queue = null,
    ): ?JobInterface {
        $this->declare($queue);

        $queueName = $queue ?? $this->defaultQueue;
        $channel = $this->connection->channel();

        while (($message = $channel->basic_get($queueName)) !== null) {
            try {
                [$job, $jobId] = $this->decodeMessage($message, $queueName);
            } catch (SerializationException|RabbitmqException $e) {
                $this->rejectUndecodableMessage($channel, $message, $queueName, $e);

                continue;
            }

            $this->deliveryTags[$jobId] = $message->getDeliveryTag();
            $this->queueNames[$jobId] = $queueName;

            return $job;
        }

        return null;
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
        $this->forget($jobId);

        return true;
    }

    /**
     * Republish the given job (its attempts, exceptions and other state as the worker left them),
     * then acknowledge the original message.
     *
     * The retry is published first and confirmed by the broker (publisher confirms) before the
     * original is acked, so a failed publish or a crash in between can duplicate the job but never
     * lose it. If the broker nacks the retry, the original is requeued and a RabbitmqException is thrown.
     *
     * @throws Exception
     */
    public function release(
        JobInterface $job,
        int $delay = 0,
    ): bool {
        $jobId = $job->id;

        if ($jobId === null || !isset($this->deliveryTags[$jobId])) {
            return false;
        }

        $deliveryTag = $this->deliveryTags[$jobId];
        $channel = $this->connection->channel();
        $queueName = $this->queueNames[$jobId];

        $updatedBody = $this->jobEnvelope->wrap($job->serialize());

        $this->enableConfirms($channel);
        $this->publishNacked = false;

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

        $channel->wait_for_pending_acks(self::CONFIRM_TIMEOUT_SECONDS);

        if ($this->publishNacked) {
            $channel->basic_nack($deliveryTag, requeue: true);
            $this->forget($jobId);

            throw RabbitmqException::republishNotConfirmed($jobId, $queueName);
        }

        $channel->basic_ack($deliveryTag);
        $this->forget($jobId);

        return true;
    }

    /**
     * Turn a popped message into its job and job ID.
     *
     * @return array{0: JobInterface, 1: string}
     *
     * @throws SerializationException|RabbitmqException
     */
    private function decodeMessage(
        AMQPMessage $message,
        string $queueName,
    ): array {
        $headers = $message->has('application_headers') ? $message->get('application_headers') : null;

        if (!$headers instanceof AMQPTable) {
            throw RabbitmqException::malformedMessage($queueName, 'it has no application_headers table');
        }

        $jobId = $headers->getNativeData()['job_id'] ?? null;

        if (!is_string($jobId) || $jobId === '') {
            throw RabbitmqException::malformedMessage(
                $queueName,
                'its job_id header is missing or not a non-empty string',
            );
        }

        $job = unserialize($this->jobEnvelope->verifyAndUnwrap($message->getBody()));

        if (!$job instanceof JobInterface) {
            throw RabbitmqException::malformedMessage(
                $queueName,
                'its payload is not a serialized ' . JobInterface::class,
            );
        }

        $job->setId($jobId);

        return [$job, $jobId];
    }

    /**
     * Reject a message that cannot be decoded, without requeueing it, and log why.
     *
     * An empty signing key is a configuration error, not a bad message: the message is requeued
     * and the error is thrown, so no queued job is dropped because the key is missing.
     *
     * @throws Exception
     */
    private function rejectUndecodableMessage(
        AMQPChannel $channel,
        AMQPMessage $message,
        string $queueName,
        SerializationException|RabbitmqException $reason,
    ): void {
        try {
            $this->jobEnvelope->wrap('');
        } catch (SerializationException $keyError) {
            $channel->basic_nack($message->getDeliveryTag(), requeue: true);

            throw $keyError;
        }

        $channel->basic_reject($message->getDeliveryTag(), false);

        error_log(sprintf(
            "[marko/queue-rabbitmq] Rejected message %d on queue '%s' without requeue"
                . ' (dead-lettered if the queue has a dead-letter exchange): %s %s',
            $message->getDeliveryTag(),
            $queueName,
            $reason->getMessage(),
            $reason->getContext(),
        ));
    }

    /**
     * Put the channel in publisher-confirm mode once, recording any message the broker nacks.
     */
    private function enableConfirms(
        AMQPChannel $channel,
    ): void {
        if ($this->confirmsEnabled) {
            return;
        }

        $channel->set_ack_handler(static function (): void {});
        $channel->set_nack_handler(function (): void {
            $this->publishNacked = true;
        });
        $channel->confirm_select();

        $this->confirmsEnabled = true;
    }

    /**
     * Drop everything tracked for a job once its message has been acked or requeued.
     */
    private function forget(
        string $jobId,
    ): void {
        unset($this->deliveryTags[$jobId], $this->queueNames[$jobId]);
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
