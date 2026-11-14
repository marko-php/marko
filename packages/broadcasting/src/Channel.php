<?php

declare(strict_types=1);

namespace Marko\Broadcasting;

use Marko\Broadcasting\Exceptions\BroadcastException;

/**
 * A public channel: anyone may subscribe without authorization.
 */
readonly class Channel
{
    /**
     * Characters a channel name must never contain: URI-template braces, the wildcard and list
     * separators drivers use as selector syntax, whitespace, and control characters.
     */
    public const string FORBIDDEN_CHARACTERS_PATTERN = '/[{}*,\s\x00-\x1F\x7F]/';

    /**
     * @throws BroadcastException
     */
    public function __construct(
        public string $name,
    ) {
        if ($this->name === '') {
            throw BroadcastException::emptyChannelName();
        }

        if (preg_match(self::FORBIDDEN_CHARACTERS_PATTERN, $this->name) === 1) {
            throw BroadcastException::unsafeChannelName($this->name);
        }
    }

    /**
     * Normalize a channel argument: plain strings become public channels.
     *
     * @throws BroadcastException
     */
    public static function from(
        string|self $channel,
    ): self {
        return $channel instanceof self ? $channel : new self($channel);
    }

    public function isPrivate(): bool
    {
        return false;
    }

    public function isPresence(): bool
    {
        return false;
    }
}
