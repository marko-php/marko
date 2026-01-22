<?php

declare(strict_types=1);

namespace Marko\Log\Contracts;

use Marko\Log\LogRecord;

interface LogFormatterInterface
{
    /**
     * Format a log record into a string.
     */
    public function format(
        LogRecord $record,
    ): string;
}
