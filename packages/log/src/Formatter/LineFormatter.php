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
        private bool $escapeNewlines = true,
    ) {}

    public function format(
        LogRecord $record,
    ): string {
        $output = $this->format;

        $message = $record->interpolatedMessage();
        $context = $record->contextAsJson();

        if ($this->escapeNewlines) {
            $message = str_replace(["\r", "\n"], ['\\r', '\\n'], $message);
            $context = str_replace(["\r", "\n"], ['\\r', '\\n'], $context);
        }

        $output = str_replace('{datetime}', $record->datetime->format($this->dateFormat), $output);
        $output = str_replace('{channel}', $record->channel, $output);
        $output = str_replace('{level}', $record->level->upperName(), $output);
        $output = str_replace('{message}', $message, $output);
        $output = str_replace('{context}', $context, $output);

        // Trim trailing whitespace from empty context
        return rtrim($output) . "\n";
    }
}
