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
        if ($this->event !== null && (str_contains($this->event, "\n") || str_contains($this->event, "\r"))) {
            throw SseException::invalidField('event', $this->event);
        }

        if (is_string($this->id) && (str_contains($this->id, "\n") || str_contains($this->id, "\r"))) {
            throw SseException::invalidField('id', $this->id);
        }
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

        foreach (explode("\n", $data) as $line) {
            $output .= "data: $line\n";
        }

        return $output . "\n";
    }
}
