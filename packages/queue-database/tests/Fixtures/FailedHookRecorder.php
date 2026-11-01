<?php

declare(strict_types=1);

namespace Marko\Queue\Database\Tests\Fixtures;

use Marko\Database\Connection\TransactionInterface;
use Throwable;

/**
 * Service a FailableCrashJob resolves from the container in failed(), recording each call and
 * whether the queue's connection was inside a transaction at the time.
 */
class FailedHookRecorder
{
    /** @var list<array{message: string, exception: Throwable, inTransaction: bool}> */
    public array $calls = [];

    public function __construct(
        private readonly TransactionInterface $connection,
    ) {}

    public function record(
        string $message,
        Throwable $exception,
    ): void {
        $this->calls[] = [
            'message' => $message,
            'exception' => $exception,
            'inTransaction' => $this->connection->inTransaction(),
        ];
    }
}
