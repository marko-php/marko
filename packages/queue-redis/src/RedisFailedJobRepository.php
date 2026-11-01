<?php

declare(strict_types=1);

namespace Marko\Queue\Redis;

use DateMalformedStringException;
use DateTimeImmutable;
use JsonException;
use Marko\Queue\FailedJob;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Redis\Exceptions\RedisConnectionException;
use Marko\Redis\RedisConnectionFactoryInterface;
use Predis\ClientInterface;

/**
 * Stores failed jobs in Redis: a hash at {prefix}failed_jobs maps each id to the job as JSON, and a sorted set
 * at {prefix}failed_jobs:index scores each id by when it failed, so all() lists them newest first. Both are
 * written and removed in one MULTI/EXEC transaction.
 */
class RedisFailedJobRepository implements FailedJobRepositoryInterface
{
    private const string FAILED_AT_FORMAT = 'Y-m-d\TH:i:s.uP';

    public function __construct(
        private readonly RedisConnectionFactoryInterface $connectionFactory,
        private readonly RedisQueueConfig $config,
    ) {}

    /**
     * @throws JsonException|RedisConnectionException
     */
    public function store(
        FailedJob $failedJob,
    ): void {
        $json = json_encode([
            'id' => $failedJob->id,
            'queue' => $failedJob->queue,
            'payload' => $failedJob->payload,
            'exception' => $failedJob->exception,
            'failedAt' => $failedJob->failedAt->format(self::FAILED_AT_FORMAT),
        ], JSON_THROW_ON_ERROR);
        $score = (float) $failedJob->failedAt->format('U.u');

        $this->client()->transaction(function ($transaction) use ($failedJob, $json, $score): void {
            $transaction->hset($this->hashKey(), $failedJob->id, $json);
            $transaction->zadd($this->indexKey(), [$failedJob->id => $score]);
        });
    }

    /**
     * @return list<FailedJob>
     *
     * @throws DateMalformedStringException|JsonException|RedisConnectionException
     */
    public function all(): array
    {
        $client = $this->client();
        $ids = $client->zrevrange($this->indexKey(), 0, -1);

        if ($ids === []) {
            return [];
        }

        $failedJobs = [];

        foreach ($client->hmget($this->hashKey(), $ids) as $json) {
            if ($json !== null) {
                $failedJobs[] = $this->hydrate($json);
            }
        }

        return $failedJobs;
    }

    /**
     * @throws DateMalformedStringException|JsonException|RedisConnectionException
     */
    public function find(
        string $id,
    ): ?FailedJob {
        $json = $this->client()->hget($this->hashKey(), $id);

        return $json === null ? null : $this->hydrate($json);
    }

    /**
     * @throws RedisConnectionException
     */
    public function delete(
        string $id,
    ): bool {
        $results = $this->client()->transaction(function ($transaction) use ($id): void {
            $transaction->hdel($this->hashKey(), [$id]);
            $transaction->zrem($this->indexKey(), $id);
        });

        return (int) $results[0] > 0;
    }

    /**
     * @throws RedisConnectionException
     */
    public function clear(): int
    {
        $results = $this->client()->transaction(function ($transaction): void {
            $transaction->hlen($this->hashKey());
            $transaction->del([$this->hashKey(), $this->indexKey()]);
        });

        return (int) $results[0];
    }

    /**
     * @throws RedisConnectionException
     */
    public function count(): int
    {
        return (int) $this->client()->hlen($this->hashKey());
    }

    /**
     * @throws DateMalformedStringException|JsonException
     */
    private function hydrate(
        string $json,
    ): FailedJob {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return new FailedJob(
            id: $data['id'],
            queue: $data['queue'],
            payload: $data['payload'],
            exception: $data['exception'],
            failedAt: new DateTimeImmutable($data['failedAt']),
        );
    }

    private function hashKey(): string
    {
        return $this->config->prefix() . 'failed_jobs';
    }

    private function indexKey(): string
    {
        return $this->config->prefix() . 'failed_jobs:index';
    }

    /**
     * @throws RedisConnectionException
     */
    private function client(): ClientInterface
    {
        return $this->connectionFactory->connection($this->config->connection())->client();
    }
}
