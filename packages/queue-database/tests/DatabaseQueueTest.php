<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Queue\Database\DatabaseQueue;
use Marko\Queue\Database\Tests\Fixtures\TestJob;
use Marko\Queue\QueueInterface;

test('DatabaseQueue implements QueueInterface', function () {
    $connection = $this->createMock(ConnectionInterface::class);
    $queue = new DatabaseQueue($connection);

    expect($queue)->toBeInstanceOf(QueueInterface::class);
});

test('DatabaseQueue push stores job in database', function () {
    $connection = $this->createMock(ConnectionInterface::class);

    $connection->expects($this->once())
        ->method('execute')
        ->with(
            $this->stringContains('INSERT INTO jobs'),
            $this->callback(function (array $bindings) {
                return isset($bindings['id'])
                    && isset($bindings['queue'])
                    && isset($bindings['payload'])
                    && isset($bindings['attempts'])
                    && isset($bindings['available_at'])
                    && isset($bindings['created_at'])
                    && $bindings['queue'] === 'default'
                    && $bindings['attempts'] === 0;
            }),
        )
        ->willReturn(1);

    $job = new TestJob('test message');

    $queue = new DatabaseQueue($connection);
    $queue->push($job);
});

test('DatabaseQueue push returns job ID', function () {
    $connection = $this->createMock(ConnectionInterface::class);
    $connection->method('execute')->willReturn(1);

    $job = new TestJob('test message');

    $queue = new DatabaseQueue($connection);
    $id = $queue->push($job);

    expect($id)->toBeString();
    expect($id)->toMatch('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/');
    expect($job->getId())->toBe($id);
});

test('DatabaseQueue later stores job with future available_at', function () {
    $connection = $this->createMock(ConnectionInterface::class);

    $capturedBindings = [];
    $connection->expects($this->once())
        ->method('execute')
        ->with(
            $this->stringContains('INSERT INTO jobs'),
            $this->callback(function (array $bindings) use (&$capturedBindings) {
                $capturedBindings = $bindings;

                return true;
            }),
        )
        ->willReturn(1);

    $job = new TestJob('delayed job');

    $queue = new DatabaseQueue($connection);
    $delay = 60; // 60 seconds
    $beforeTime = new DateTimeImmutable();
    $id = $queue->later($delay, $job);
    $afterTime = new DateTimeImmutable();

    expect($id)->toBeString();

    $availableAt = new DateTimeImmutable($capturedBindings['available_at']);
    $expectedMin = $beforeTime->modify('+59 seconds');
    $expectedMax = $afterTime->modify('+61 seconds');

    expect($availableAt >= $expectedMin)->toBeTrue('available_at should be at least 59 seconds in future');
    expect($availableAt <= $expectedMax)->toBeTrue('available_at should be at most 61 seconds in future');
});

test('DatabaseQueue pop retrieves and reserves next job', function () {
    $connection = $this->createMock(ConnectionInterface::class);

    $job = new TestJob('pop test');
    $serializedJob = $job->serialize();

    // First query: SELECT to find next available job
    // Second execute: UPDATE to reserve the job
    $connection->expects($this->once())
        ->method('query')
        ->with(
            $this->stringContains('SELECT'),
            $this->callback(function (array $bindings) {
                return isset($bindings['queue']);
            }),
        )
        ->willReturn([
            [
                'id' => 'job-123',
                'queue' => 'default',
                'payload' => $serializedJob,
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => '2024-01-01 00:00:00',
                'created_at' => '2024-01-01 00:00:00',
            ],
        ]);

    $connection->expects($this->once())
        ->method('execute')
        ->with(
            $this->stringContains('UPDATE'),
            $this->callback(function (array $bindings) {
                return isset($bindings['reserved_at'])
                    && isset($bindings['attempts'])
                    && $bindings['attempts'] === 1
                    && $bindings['id'] === 'job-123';
            }),
        )
        ->willReturn(1);

    $queue = new DatabaseQueue($connection);
    $poppedJob = $queue->pop();

    expect($poppedJob)->toBeInstanceOf(TestJob::class);
    expect($poppedJob->getId())->toBe('job-123');
    expect($poppedJob->getAttempts())->toBe(1);
});

test('DatabaseQueue pop returns null when empty', function () {
    $connection = $this->createMock(ConnectionInterface::class);

    $connection->expects($this->once())
        ->method('query')
        ->willReturn([]);

    $connection->expects($this->never())
        ->method('execute');

    $queue = new DatabaseQueue($connection);
    $result = $queue->pop();

    expect($result)->toBeNull();
});

test('DatabaseQueue size returns pending job count', function () {
    $connection = $this->createMock(ConnectionInterface::class);

    $connection->expects($this->once())
        ->method('query')
        ->with(
            $this->stringContains('COUNT'),
            $this->callback(function (array $bindings) {
                return isset($bindings['queue']) && $bindings['queue'] === 'default';
            }),
        )
        ->willReturn([['count' => 5]]);

    $queue = new DatabaseQueue($connection);
    $size = $queue->size();

    expect($size)->toBe(5);
});

test('DatabaseQueue clear removes all jobs', function () {
    $connection = $this->createMock(ConnectionInterface::class);

    $connection->expects($this->once())
        ->method('execute')
        ->with(
            $this->stringContains('DELETE'),
            $this->callback(function (array $bindings) {
                return isset($bindings['queue']) && $bindings['queue'] === 'default';
            }),
        )
        ->willReturn(10);

    $queue = new DatabaseQueue($connection);
    $cleared = $queue->clear();

    expect($cleared)->toBe(10);
});

test('DatabaseQueue delete removes specific job', function () {
    $connection = $this->createMock(ConnectionInterface::class);

    $connection->expects($this->once())
        ->method('execute')
        ->with(
            $this->stringContains('DELETE'),
            $this->callback(function (array $bindings) {
                return isset($bindings['id']) && $bindings['id'] === 'job-123';
            }),
        )
        ->willReturn(1);

    $queue = new DatabaseQueue($connection);
    $deleted = $queue->delete('job-123');

    expect($deleted)->toBeTrue();
});

test('DatabaseQueue delete returns false when job not found', function () {
    $connection = $this->createMock(ConnectionInterface::class);

    $connection->expects($this->once())
        ->method('execute')
        ->willReturn(0);

    $queue = new DatabaseQueue($connection);
    $deleted = $queue->delete('nonexistent-job');

    expect($deleted)->toBeFalse();
});

test('DatabaseQueue release updates job availability', function () {
    $connection = $this->createMock(ConnectionInterface::class);

    $capturedBindings = [];
    $connection->expects($this->once())
        ->method('execute')
        ->with(
            $this->stringContains('UPDATE'),
            $this->callback(function (array $bindings) use (&$capturedBindings) {
                $capturedBindings = $bindings;

                return array_key_exists('id', $bindings)
                    && array_key_exists('reserved_at', $bindings)
                    && array_key_exists('available_at', $bindings)
                    && $bindings['id'] === 'job-123'
                    && $bindings['reserved_at'] === null;
            }),
        )
        ->willReturn(1);

    $queue = new DatabaseQueue($connection);
    $beforeTime = new DateTimeImmutable();
    $released = $queue->release('job-123', 30);
    $afterTime = new DateTimeImmutable();

    expect($released)->toBeTrue();

    $availableAt = new DateTimeImmutable($capturedBindings['available_at']);
    $expectedMin = $beforeTime->modify('+29 seconds');
    $expectedMax = $afterTime->modify('+31 seconds');

    expect($availableAt >= $expectedMin)->toBeTrue();
    expect($availableAt <= $expectedMax)->toBeTrue();
});

test('DatabaseQueue release with zero delay makes job immediately available', function () {
    $connection = $this->createMock(ConnectionInterface::class);

    $capturedBindings = [];
    $connection->expects($this->once())
        ->method('execute')
        ->with(
            $this->stringContains('UPDATE'),
            $this->callback(function (array $bindings) use (&$capturedBindings) {
                $capturedBindings = $bindings;

                return true;
            }),
        )
        ->willReturn(1);

    $queue = new DatabaseQueue($connection);
    $beforeTime = new DateTimeImmutable();
    $released = $queue->release('job-123', 0);
    $afterTime = new DateTimeImmutable();

    expect($released)->toBeTrue();

    $availableAt = new DateTimeImmutable($capturedBindings['available_at']);

    // Allow 1 second tolerance for timing differences
    expect($availableAt->getTimestamp())->toBeGreaterThanOrEqual($beforeTime->getTimestamp() - 1);
    expect($availableAt->getTimestamp())->toBeLessThanOrEqual($afterTime->getTimestamp() + 1);
});

test('DatabaseQueue release returns false when job not found', function () {
    $connection = $this->createMock(ConnectionInterface::class);

    $connection->expects($this->once())
        ->method('execute')
        ->willReturn(0);

    $queue = new DatabaseQueue($connection);
    $released = $queue->release('nonexistent-job');

    expect($released)->toBeFalse();
});
