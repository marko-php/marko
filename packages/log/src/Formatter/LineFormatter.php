<?php

declare(strict_types=1);

namespace Marko\Log\Formatter;

use Marko\Log\Contracts\LogFormatterInterface;
use Marko\Log\LogRecord;

readonly class LineFormatter implements LogFormatterInterface
{
    public function __construct(
        private string $format = '[{datetime}] {channel}.{level}: {message} {context}',
        private string $dateFormat = 'Y-m-d H:i:s',
    ) {}

    public function format(
        LogRecord $record,
    ): string {
        $output = $this->format;

        $output = str_replace('{datetime}', $record->datetime->format($this->dateFormat), $output);
        $output = str_replace('{channel}', $record->channel, $output);
        $output = str_replace('{level}', $record->level->upperName(), $output);
        $output = str_replace('{message}', $record->interpolatedMessage(), $output);
        $output = str_replace('{context}', $record->contextAsJson(), $output);

        // Trim trailing whitespace from empty context
        return rtrim($output) . "\n";
    }
}
