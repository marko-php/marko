<?php

declare(strict_types=1);

namespace Marko\Debugbar\Access;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Environment\AppEnvironment;

/**
 * Decides whether the debugbar may run at all, and whether the current client may see it.
 *
 * Both the injected toolbar and the /_debugbar profiler routes go through this gate, so the
 * production hard-stop and the IP allowlist apply to every surface that exposes captured data.
 */
class AccessGate
{
    /** @var list<string> */
    private const array DEFAULT_ALLOWED_IPS = ['127.0.0.1', '::1'];

    /** @var list<string> */
    private const array FORWARDING_HEADERS = ['HTTP_X_FORWARDED_FOR', 'HTTP_FORWARDED'];

    public function __construct(
        private readonly ConfigRepositoryInterface $config,
        private readonly AppEnvironment $environment = new AppEnvironment(),
    ) {}

    /**
     * True when `debugbar.enabled` is on and the app is not running in production,
     * unless production has been explicitly allowed with `debugbar.allow_production`.
     */
    public function enabled(): bool
    {
        if (! $this->configBool('debugbar.enabled', false)) {
            return false;
        }

        if (
            $this->environment->isProduction()
            && ! $this->configBool('debugbar.allow_production', false)
        ) {
            return false;
        }

        return true;
    }

    /**
     * True when the client described by $server may see captured debug data.
     *
     * A missing REMOTE_ADDR denies. Forwarding headers from a peer that is not a configured
     * trusted proxy deny, because a same-host reverse proxy makes every visitor look like
     * loopback. Behind a trusted proxy the right-most untrusted forwarded hop is checked
     * against the allowlist instead of the proxy's own address.
     *
     * @param array<mixed> $server
     */
    public function clientAllowed(
        array $server,
    ): bool {
        if ($this->configBool('debugbar.route.open', false)) {
            return true;
        }

        $remoteAddress = $server['REMOTE_ADDR'] ?? null;

        if (
            ! is_string($remoteAddress)
            || filter_var($remoteAddress, FILTER_VALIDATE_IP) === false
        ) {
            return false;
        }

        $client = $remoteAddress;

        if ($this->hasForwardingHeaders($server)) {
            $trustedProxies = $this->configList('debugbar.route.trusted_proxies', []);

            if (! $this->matches($remoteAddress, $trustedProxies)) {
                return false;
            }

            $client = $this->forwardedClient($server, $trustedProxies);

            if ($client === null) {
                return false;
            }
        }

        return $this->matches(
            $client,
            $this->configList('debugbar.route.allowed_ips', self::DEFAULT_ALLOWED_IPS),
        );
    }

    /**
     * @param array<mixed> $server
     */
    private function hasForwardingHeaders(
        array $server,
    ): bool {
        foreach (self::FORWARDING_HEADERS as $header) {
            if (array_key_exists($header, $server)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Walk every forwarded hop from right to left and return the first one that is not a
     * trusted proxy. A hop that is not a valid IP address makes the whole chain untrustworthy.
     *
     * @param array<mixed> $server
     * @param list<string> $trustedProxies
     */
    private function forwardedClient(
        array $server,
        array $trustedProxies,
    ): ?string {
        $hops = [];

        $forwarded = $server['HTTP_FORWARDED'] ?? null;

        if (is_string($forwarded)) {
            foreach (explode(',', $forwarded) as $element) {
                $hops[] = $this->forwardedFor($element);
            }
        }

        $forwardedFor = $server['HTTP_X_FORWARDED_FOR'] ?? null;

        if (is_string($forwardedFor)) {
            foreach (explode(',', $forwardedFor) as $entry) {
                $hops[] = trim($entry);
            }
        }

        foreach (array_reverse($hops) as $hop) {
            if (
                $hop === null
                || filter_var($hop, FILTER_VALIDATE_IP) === false
            ) {
                return null;
            }

            if (! $this->matches($hop, $trustedProxies)) {
                return $hop;
            }
        }

        return null;
    }

    /**
     * Extract the address from the `for=` parameter of one RFC 7239 `Forwarded` element.
     */
    private function forwardedFor(
        string $element,
    ): ?string {
        foreach (explode(';', $element) as $pair) {
            $parts = explode('=', trim($pair), 2);

            if (
                count($parts) !== 2
                || strtolower(trim($parts[0])) !== 'for'
            ) {
                continue;
            }

            $value = trim(trim($parts[1]), '"');

            if (str_starts_with($value, '[')) {
                $end = strpos($value, ']');

                return $end === false ? null : substr($value, 1, $end - 1);
            }

            if (substr_count($value, ':') === 1) {
                return explode(':', $value)[0];
            }

            return $value;
        }

        return null;
    }

    /**
     * @param list<string> $addresses
     */
    private function matches(
        string $ip,
        array $addresses,
    ): bool {
        $normalized = $this->normalize($ip);

        foreach ($addresses as $address) {
            if ($this->normalize($address) === $normalized) {
                return true;
            }
        }

        return false;
    }

    private function normalize(
        string $ip,
    ): string {
        $ip = strtolower(trim($ip));
        $packed = filter_var($ip, FILTER_VALIDATE_IP) === false ? false : inet_pton($ip);

        if ($packed === false) {
            return $ip;
        }

        $normalized = inet_ntop($packed);

        return $normalized === false ? $ip : $normalized;
    }

    private function configBool(
        string $key,
        bool $default,
    ): bool {
        if (! $this->config->has($key)) {
            return $default;
        }

        $value = $this->config->get($key);

        if (is_bool($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            return $normalized ?? (bool) $value;
        }

        return $default;
    }

    /**
     * @param list<string> $default
     * @return list<string>
     */
    private function configList(
        string $key,
        array $default,
    ): array {
        if (! $this->config->has($key)) {
            return $default;
        }

        $value = $this->config->get($key);

        if (! is_array($value)) {
            return $default;
        }

        return array_values(array_map('strval', array_filter($value, 'is_scalar')));
    }
}
