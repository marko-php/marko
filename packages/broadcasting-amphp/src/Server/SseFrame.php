<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Amphp\Server;

/**
 * Control frames the server writes besides published events.
 *
 * EventSource only dispatches events that carry a data field, so reset and reconnect include one.
 */
class SseFrame
{
    /**
     * An SSE comment; keeps proxies and the HTTP driver from timing out idle streams.
     */
    public const string HEARTBEAT = ":\n\n";

    /**
     * Sent instead of a replay when the client's Last-Event-ID cannot be replayed; refetch state.
     */
    public static function reset(
        string $reason,
    ): string {
        return "event: reset\ndata: {\"reason\":\"$reason\"}\n\n";
    }

    /**
     * Sent before the server closes streams on shutdown; the browser reconnects after $retryMs.
     */
    public static function reconnect(
        int $retryMs,
    ): string {
        return "retry: $retryMs\nevent: reconnect\ndata: {}\n\n";
    }
}
