<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Config;

use Marko\AuthenticationPasskey\Exceptions\PasskeyConfigurationException;
use Marko\Config\ConfigRepositoryInterface;

/**
 * Typed, validated access to config/authentication-passkey.php. Every value
 * is required and checked when read; nothing falls back to a default here.
 */
readonly class PasskeyConfig
{
    private const string DOMAIN_PATTERN = '/^(?=.{1,253}$)[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/';

    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    /**
     * The relying party id: a lower-case domain without scheme or port.
     *
     * @throws PasskeyConfigurationException
     */
    public function relyingPartyId(): string
    {
        $id = $this->config->get('authentication-passkey.relying_party.id');

        if (!is_string($id) || trim($id) === '') {
            throw PasskeyConfigurationException::invalidSetting(
                'relying_party.id',
                'must be set to your site\'s domain',
                $id,
                'Set PASSKEY_RP_ID (or authentication-passkey.relying_party.id) to the domain users sign in on, e.g. example.com',
            );
        }

        $id = strtolower(trim($id));

        if (preg_match(self::DOMAIN_PATTERN, $id) !== 1 || filter_var($id, FILTER_VALIDATE_IP) !== false) {
            throw PasskeyConfigurationException::invalidSetting(
                'relying_party.id',
                'must be a domain name without scheme, port or path',
                $id,
                'Use the bare domain, e.g. example.com rather than https://example.com:443/. IP addresses cannot be relying party ids; use localhost while developing',
            );
        }

        return $id;
    }

    /**
     * The name browsers show while the user creates a passkey.
     *
     * @throws PasskeyConfigurationException
     */
    public function relyingPartyName(): string
    {
        $name = $this->config->get('authentication-passkey.relying_party.name');

        if (!is_string($name) || trim($name) === '') {
            throw PasskeyConfigurationException::invalidSetting(
                'relying_party.name',
                'must be set to your site\'s name',
                $name,
                'Set PASSKEY_RP_NAME (or authentication-passkey.relying_party.name) to the name users know your site by, e.g. "Acme"',
            );
        }

        return trim($name);
    }

    /**
     * The exact origins passkey responses may come from, normalised to
     * scheme://host[:port] in lower case.
     *
     * @return list<string>
     * @throws PasskeyConfigurationException
     */
    public function allowedOrigins(): array
    {
        $origins = $this->config->get('authentication-passkey.allowed_origins');

        if (!is_array($origins) || $origins === [] || !array_is_list($origins)) {
            throw PasskeyConfigurationException::invalidSetting(
                'allowed_origins',
                'must be a non-empty list of origins',
                $origins,
                'Set PASSKEY_ALLOWED_ORIGINS (comma-separated) or authentication-passkey.allowed_origins to the origins your passkey pages are served from, e.g. https://example.com',
            );
        }

        $relyingPartyId = $this->relyingPartyId();

        return array_map(
            fn (mixed $origin): string => $this->normaliseOrigin($origin, $relyingPartyId),
            $origins,
        );
    }

    /**
     * The session guard passkeys sign users in with.
     *
     * @throws PasskeyConfigurationException
     */
    public function guard(): string
    {
        $guard = $this->config->get('authentication-passkey.guard');

        if (!is_string($guard) || $guard === '') {
            throw PasskeyConfigurationException::invalidSetting(
                'guard',
                'must name a guard from authentication.guards',
                $guard,
                'Set authentication-passkey.guard to a session guard, e.g. "session"',
            );
        }

        return $guard;
    }

    /**
     * @throws PasskeyConfigurationException
     */
    public function timeoutSeconds(): int
    {
        return $this->positiveInt('authentication-passkey.timeout_seconds', 'e.g. 300 for five minutes');
    }

    /**
     * @throws PasskeyConfigurationException
     */
    public function throttleMaxAttempts(): int
    {
        return $this->positiveInt('authentication-passkey.throttle.max_attempts', 'e.g. 5');
    }

    /**
     * @throws PasskeyConfigurationException
     */
    public function throttleDecaySeconds(): int
    {
        return $this->positiveInt('authentication-passkey.throttle.decay_seconds', 'e.g. 60');
    }

    /**
     * @throws PasskeyConfigurationException
     */
    private function positiveInt(
        string $key,
        string $example,
    ): int {
        $value = $this->config->get($key);

        if (!is_int($value) || $value < 1) {
            throw PasskeyConfigurationException::invalidSetting(
                substr($key, strlen('authentication-passkey.')),
                'must be a positive integer',
                $value,
                "Set $key to a whole number of 1 or more, $example",
            );
        }

        return $value;
    }

    /**
     * @throws PasskeyConfigurationException
     */
    private function normaliseOrigin(
        mixed $origin,
        string $relyingPartyId,
    ): string {
        $parts = is_string($origin) ? parse_url(strtolower(trim($origin))) : false;

        if (
            !is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || array_any(['user', 'pass', 'query', 'fragment'], fn (string $part): bool => isset($parts[$part]))
            || ($parts['path'] ?? '') !== ''
        ) {
            throw PasskeyConfigurationException::invalidSetting(
                'allowed_origins',
                'must contain origins of the form scheme://host[:port]',
                $origin,
                'List each origin without a path, query or trailing slash, e.g. https://example.com or https://app.example.com:8443',
            );
        }

        $host = $parts['host'];
        $isLocalhost = $host === 'localhost' || str_ends_with($host, '.localhost');

        if ($parts['scheme'] !== 'https' && !($parts['scheme'] === 'http' && $isLocalhost)) {
            throw PasskeyConfigurationException::invalidSetting(
                'allowed_origins',
                'must only contain https origins (http is accepted for localhost alone)',
                $origin,
                'Serve passkey pages over https; browsers refuse WebAuthn on plain http anywhere but localhost',
            );
        }

        if ($host !== $relyingPartyId && !str_ends_with($host, '.' . $relyingPartyId)) {
            throw PasskeyConfigurationException::invalidSetting(
                'allowed_origins',
                "must only contain origins on the relying party id '$relyingPartyId' or its subdomains",
                $origin,
                'Remove the origin, or change authentication-passkey.relying_party.id to a domain that covers it',
            );
        }

        // Browsers leave the scheme's default port out of an origin, so drop it here too.
        $defaultPort = $parts['scheme'] === 'https' ? 443 : 80;
        $port = isset($parts['port']) && $parts['port'] !== $defaultPort ? ':' . $parts['port'] : '';

        return $parts['scheme'] . '://' . $host . $port;
    }
}
