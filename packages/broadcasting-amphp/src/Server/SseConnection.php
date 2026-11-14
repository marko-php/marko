<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Amphp\Server;

use Amp\ByteStream\ReadableIterableStream;
use Amp\Pipeline\Queue;
use Closure;

/**
 * One open EventSource stream. Frames are queued without ever blocking the sender: a client
 * that falls more than MAX_PENDING_FRAMES behind is closed so one slow reader cannot stall
 * fan-out or grow memory without bound.
 */
class SseConnection
{
    public const int MAX_PENDING_FRAMES = 500;

    /**
     * @var Queue<string>
     */
    private readonly Queue $queue;

    private readonly ReadableIterableStream $stream;

    private int $pending = 0;

    private bool $closed = false;

    /**
     * @var list<Closure(self): void>
     */
    private array $onClose = [];

    /**
     * @param list<string> $channels Stream channel names (private ones carry the private- prefix)
     */
    public function __construct(
        public readonly int $id,
        public readonly array $channels,
        public readonly string $ip,
    ) {
        $this->queue = new Queue();
        $this->stream = new ReadableIterableStream($this->queue->pipe());
    }

    /**
     * The response body the HTTP server reads frames from.
     */
    public function stream(): ReadableIterableStream
    {
        return $this->stream;
    }

    /**
     * Queue a frame; returns false when the connection is closed or was just closed for lagging.
     */
    public function send(
        string $frame,
    ): bool {
        if ($this->closed) {
            return false;
        }

        if ($this->pending >= self::MAX_PENDING_FRAMES) {
            $this->close();

            return false;
        }

        $this->pending++;
        $this->queue->pushAsync($frame)
            ->finally(function (): void {
                $this->pending--;
            })
            ->ignore();

        return true;
    }

    /**
     * End the stream, optionally after a last frame. Safe to call more than once.
     */
    public function close(
        ?string $finalFrame = null,
    ): void {
        if ($this->closed) {
            return;
        }

        if ($finalFrame !== null) {
            $this->queue->pushAsync($finalFrame)->ignore();
        }

        $this->closed = true;

        if (!$this->queue->isComplete()) {
            $this->queue->complete();
        }

        foreach ($this->onClose as $callback) {
            $callback($this);
        }

        $this->onClose = [];
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function pendingFrames(): int
    {
        return $this->pending;
    }

    /**
     * @param Closure(self): void $callback
     */
    public function onClose(
        Closure $callback,
    ): void {
        if ($this->closed) {
            $callback($this);

            return;
        }

        $this->onClose[] = $callback;
    }
}
