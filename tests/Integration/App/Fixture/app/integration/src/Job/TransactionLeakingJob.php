<?php

declare(strict_types=1);

namespace Marko\Integration\Fixture\Job;

use Marko\Core\Container\ContainerInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Queue\ContainerAwareJobInterface;
use Marko\Queue\FailableJobInterface;
use Marko\Queue\Job;
use Marko\Queue\JobEnvelope;
use RuntimeException;
use Throwable;

/**
 * Opens a transaction, writes an authors row inside it and then leaves the
 * transaction open: by throwing, by ending its own database session, or by
 * returning. Its failed() hook writes a marker row, so a test can see that the
 * hook ran on a clean, committed connection.
 */
class TransactionLeakingJob extends Job implements ContainerAwareJobInterface, FailableJobInterface
{
    public const string MODE_THROW = 'throw';

    public const string MODE_TERMINATE = 'terminate';

    public const string MODE_RETURN = 'return';

    public const string LEAKED_ROW = 'Leaked inside the open transaction';

    public const string FAILED_MARKER = 'Written by failed()';

    public protected(set) ?int $maxAttempts = 1;

    private ?ContainerInterface $container = null;

    public function __construct(
        public string $mode,
    ) {}

    public function setContainer(
        ContainerInterface $container,
    ): void {
        $this->container = $container;
    }

    public function setJobEnvelope(
        JobEnvelope $jobEnvelope,
    ): void {
        // Not needed: this job carries only a mode
    }

    public function releaseContainer(): void
    {
        $this->container = null;
    }

    public function handle(): void
    {
        $container = $this->requireContainer();
        $container->get(TransactionInterface::class)->beginTransaction();
        $connection = $container->get(ConnectionInterface::class);
        $connection->execute('INSERT INTO authors (name) VALUES (:name)', ['name' => self::LEAKED_ROW]);

        if ($this->mode === self::MODE_THROW) {
            throw new RuntimeException('TransactionLeakingJob threw with its transaction open');
        }

        if ($this->mode === self::MODE_TERMINATE) {
            $connection->query('SELECT pg_terminate_backend(pg_backend_pid())');
            // Reached only when the statement above returned instead of throwing
            $connection->query('SELECT 1');
        }
    }

    public function failed(
        Throwable $exception,
    ): void {
        $this->requireContainer()->get(ConnectionInterface::class)->execute(
            'INSERT INTO authors (name) VALUES (:name)',
            ['name' => self::FAILED_MARKER],
        );
    }

    private function requireContainer(): ContainerInterface
    {
        return $this->container ?? throw new RuntimeException(
            'TransactionLeakingJob was run without a container.',
        );
    }
}
