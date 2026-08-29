<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Worker;

use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Reports a throwable through a PSR logger when one is available, otherwise
 * to STDERR. RoadRunner's default worker relay is STDIN/STDOUT, so STDERR
 * is the only stream a worker may write to without corrupting the goridge
 * protocol frames carried on STDOUT.
 */
readonly class WorkerLogger
{
    public function __construct(
        private ?LoggerInterface $logger = null,
    ) {}

    public function error(
        Throwable $throwable,
    ): void {
        $message = sprintf(
            '[worker] %s: %s in %s:%d',
            $throwable::class,
            $throwable->getMessage(),
            $throwable->getFile(),
            $throwable->getLine(),
        );

        if ($this->logger !== null) {
            $this->logger->error($message, ['exception' => $throwable]);

            return;
        }

        fwrite(STDERR, $message . PHP_EOL);
    }
}
