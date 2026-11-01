<?php

declare(strict_types=1);

namespace Marko\Log\Contracts;

use Marko\Log\Exceptions\LogWriteException;
use Marko\Log\LogRecord;

/**
 * Writes log records to one backend (a file, a stream, ...). The logger applies the level
 * threshold first, so a handler writes every record it receives.
 */
interface LogHandlerInterface
{
    /**
     * Write one record that has already passed the level threshold.
     *
     * @throws LogWriteException When the record cannot be written
     */
    public function handle(
        LogRecord $record,
    ): void;
}
