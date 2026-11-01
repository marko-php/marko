<?php

declare(strict_types=1);

namespace Marko\Log\Formatter;

use Marko\Log\Contracts\LogFormatterInterface;
use Marko\Log\LogRecord;

readonly class LineFormatter implements LogFormatterInterface
{
    public const string REDACTED = ContextRedactor::REDACTED;

    public const array DEFAULT_REDACT_KEYS = ContextRedactor::DEFAULT_KEYS;

    /**
     * C0 controls, DEL, C1 controls and the U+2028/U+2029 line terminators in valid UTF-8.
     */
    private const string UNSAFE_UTF8_PATTERN = '/[\x00-\x1f\x7f\x{80}-\x{9f}\x{2028}\x{2029}]/u';

    /**
     * Byte-level fallback for strings that are not valid UTF-8: C0, DEL and the raw C1 byte range.
     */
    private const string UNSAFE_BYTE_PATTERN = '/[\x00-\x1f\x7f-\x9f]/';

    /**
     * Context JSON already escapes C0 controls and U+2028/U+2029; DEL and C1 controls pass through raw.
     */
    private const string UNSAFE_JSON_PATTERN = '/[\x7f\x{80}-\x{9f}\x{2028}\x{2029}]/u';

    private ContextRedactor $redactor;

    /**
     * @param list<string> $redactKeys Context keys (case-insensitive, at any depth) whose values are replaced with [redacted]
     */
    public function __construct(
        private string $format = '[{datetime}] {channel}.{level}: {message} {context}',
        private string $dateFormat = 'Y-m-d H:i:s',
        private bool $escapeNewlines = true,
        array $redactKeys = self::DEFAULT_REDACT_KEYS,
    ) {
        $this->redactor = new ContextRedactor($redactKeys);
    }

    public function format(
        LogRecord $record,
    ): string {
        $output = $this->format;

        $record = $this->redactor->redact($record);
        $message = $this->escapeMessage($record->interpolatedMessage());
        $context = $this->escapeContext($record->contextAsJson());

        $output = str_replace('{datetime}', $record->datetime->format($this->dateFormat), $output);
        $output = str_replace('{channel}', $record->channel, $output);
        $output = str_replace('{level}', $record->level->upperName(), $output);
        $output = str_replace('{message}', $message, $output);
        $output = str_replace('{context}', $context, $output);

        // Trim trailing whitespace from empty context
        return rtrim($output) . "\n";
    }

    /**
     * Backslashes are escaped first so an escaped control (\n, \x1b) can never be confused with literal text.
     */
    private function escapeMessage(
        string $message,
    ): string {
        $message = str_replace('\\', '\\\\', $message);
        $pattern = preg_match('//u', $message) === 1 ? self::UNSAFE_UTF8_PATTERN : self::UNSAFE_BYTE_PATTERN;

        return (string) preg_replace_callback(
            $pattern,
            fn (array $matches): string => $this->escapeCharacter($matches[0]),
            $message,
        );
    }

    /**
     * JSON escapes backslashes and C0 controls itself; the remaining unsafe characters become \uXXXX
     * escapes, which keeps the context valid JSON.
     */
    private function escapeContext(
        string $context,
    ): string {
        return (string) preg_replace_callback(
            self::UNSAFE_JSON_PATTERN,
            fn (array $matches): string => sprintf('\\u%04x', $this->codePoint($matches[0])),
            $context,
        );
    }

    private function escapeCharacter(
        string $character,
    ): string {
        if (!$this->escapeNewlines && ($character === "\n" || $character === "\r")) {
            return $character;
        }

        return match (true) {
            $character === "\n" => '\\n',
            $character === "\r" => '\\r',
            $character === "\t" => '\\t',
            strlen($character) === 1 => sprintf('\\x%02x', ord($character)),
            default => sprintf('\\u%04x', $this->codePoint($character)),
        };
    }

    /**
     * Decodes one UTF-8 character of up to three bytes (every unsafe range fits in three).
     */
    private function codePoint(
        string $character,
    ): int {
        return match (strlen($character)) {
            1 => ord($character),
            2 => ((ord($character[0]) & 0x1F) << 6) | (ord($character[1]) & 0x3F),
            default => ((ord($character[0]) & 0x0F) << 12)
                | ((ord($character[1]) & 0x3F) << 6)
                | (ord($character[2]) & 0x3F),
        };
    }
}
