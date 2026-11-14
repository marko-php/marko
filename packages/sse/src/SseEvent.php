<?php

declare(strict_types=1);

namespace Marko\Sse;

use JsonException;
use Marko\Sse\Exceptions\SseException;
use NoDiscard;

readonly class SseEvent
{
    /**
     * @throws SseException
     */
    public function __construct(
        public string|array $data,
        public ?string $event = null,
        public string|int|null $id = null,
        public ?int $retry = null,
    ) {
        if (
            $this->event !== null
            && self::containsForbiddenCharacter($this->event)
        ) {
            throw SseException::invalidField('event', $this->event);
        }

        if (
            is_string($this->id)
            && self::containsForbiddenCharacter($this->id)
        ) {
            throw SseException::invalidField('id', $this->id);
        }
    }

    /**
     * CR and LF end an SSE field line; a NUL in `id` makes browsers ignore the field.
     */
    private static function containsForbiddenCharacter(
        string $value,
    ): bool {
        return strpbrk($value, "\r\n\0") !== false;
    }

    /**
     * @throws JsonException
     */
    #[NoDiscard]
    public function format(): string
    {
        $output = '';

        if ($this->event !== null) {
            $output .= "event: $this->event\n";
        }

        if ($this->id !== null) {
            $output .= "id: $this->id\n";
        }

        if ($this->retry !== null) {
            $output .= "retry: $this->retry\n";
        }

        $data = is_array($this->data)
            ? json_encode($this->data, JSON_THROW_ON_ERROR)
            : $this->data;

        // The SSE spec treats CRLF, lone CR and lone LF all as line terminators, so
        // every one of them must start a new `data:` line; splitting on LF alone lets
        // a bare CR in the payload end the line and inject event/id/retry fields.
        foreach (preg_split('/\r\n|\r|\n/', $data) as $line) {
            $output .= "data: $line\n";
        }

        return $output . "\n";
    }
}
