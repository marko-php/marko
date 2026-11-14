<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Shared devserver test helpers (loaded via composer autoload-dev.files)
// ---------------------------------------------------------------------------

if (!function_exists('devserverWaitUntil')) {
    /**
     * Poll a condition until it holds or the deadline passes.
     *
     * Returns true as soon as the condition holds, false when the deadline passes first.
     * Tests assert on the result instead of sleeping a fixed time and hoping the OS was fast.
     */
    function devserverWaitUntil(
        callable $condition,
        float $timeoutSeconds = 5.0,
        int $intervalMicros = 10_000,
    ): bool {
        $deadline = microtime(true) + $timeoutSeconds;

        while (true) {
            if ($condition()) {
                return true;
            }

            if (microtime(true) >= $deadline) {
                return false;
            }

            usleep($intervalMicros);
        }
    }

    /**
     * Whether any process in the given process group is still alive.
     */
    function devserverProcessGroupAlive(
        int $pgid,
    ): bool {
        return @posix_kill(-$pgid, 0);
    }

    /**
     * A unique temp file path a test process can touch to signal it is ready.
     */
    function devserverMarkerPath(): string
    {
        return sys_get_temp_dir() . '/marko-devserver-ready-' . bin2hex(random_bytes(6));
    }

    /**
     * The PID a test process wrote to a marker file, or 0 while the file is missing or still empty.
     */
    function devserverMarkerPid(
        string $marker,
    ): int {
        clearstatcache(true, $marker);

        if (!is_file($marker)) {
            return 0;
        }

        return (int) file_get_contents($marker);
    }

    /**
     * Stop a detached process group and wait until it is gone, so no process outlives the test.
     */
    function devserverStopDetached(
        int $pid,
    ): void {
        @posix_kill(-$pid, SIGTERM);

        if (!devserverWaitUntil(fn (): bool => !devserverProcessGroupAlive($pid))) {
            @posix_kill(-$pid, SIGKILL);
            devserverWaitUntil(fn (): bool => !devserverProcessGroupAlive($pid));
        }
    }

    /**
     * Listen on an ephemeral port, so tests never collide under --parallel.
     *
     * @return array{0: resource, 1: int}
     */
    function devserverListen(
        string $address = '127.0.0.1',
    ): array {
        $server = stream_socket_server("tcp://$address:0", $errno, $errstr);

        if ($server === false) {
            throw new RuntimeException("Cannot listen on $address: $errstr");
        }

        $name = (string) stream_socket_get_name($server, false);

        return [$server, (int) substr($name, (int) strrpos($name, ':') + 1)];
    }

    /**
     * A port that was free a moment ago.
     *
     * @param string $address The address to probe, with IPv6 literals bracketed
     */
    function devserverFreePort(
        string $address = '127.0.0.1',
    ): int {
        [$server, $port] = devserverListen($address);
        fclose($server);

        return $port;
    }

    /**
     * The body of a GET request, or false while nothing answers (without a warning PHPUnit would report).
     */
    function devserverHttpGet(
        string $url,
    ): string|false {
        set_error_handler(static fn (): bool => true);

        try {
            return file_get_contents($url);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Whether this machine can listen on the IPv6 loopback address.
     */
    function devserverHasIpv6Loopback(): bool
    {
        $server = @stream_socket_server('tcp://[::1]:0');

        if ($server === false) {
            return false;
        }

        fclose($server);

        return true;
    }
}
