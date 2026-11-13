<?php

declare(strict_types=1);

namespace Marko\ErrorsSimple;

use Marko\Core\Environment\AppEnvironment;

class Environment
{
    /**
     * @param array<string, string>|null $envVars
     * @param array<string, mixed>|null $server Request server variables; defaults to $_SERVER
     * @param AppEnvironment|null $appEnvironment The application environment; defaults to one built from $envVars
     */
    public function __construct(
        private ?string $sapi = null,
        private ?array $envVars = null,
        private ?array $server = null,
        private ?AppEnvironment $appEnvironment = null,
    ) {}

    public function isCli(): bool
    {
        return $this->getSapi() === 'cli';
    }

    public function isWeb(): bool
    {
        return !$this->isCli();
    }

    /**
     * Delegates to the core AppEnvironment so every package agrees on which
     * names mean development (development, dev, local).
     */
    public function isDevelopment(): bool
    {
        return $this->appEnvironment()->isDevelopment();
    }

    /**
     * The core AppEnvironment this instance delegates to: the injected one,
     * or one that reads $envVars (or the real environment when null).
     */
    public function appEnvironment(): AppEnvironment
    {
        return $this->appEnvironment ?? new AppEnvironment($this->envVars);
    }

    /**
     * Anything that is not a development environment — including an unset
     * environment or a name such as "staging" — hides error details.
     */
    public function isProduction(): bool
    {
        return !$this->isDevelopment();
    }

    /**
     * Whether a PHP warning is thrown as an ErrorException so the error page
     * shows it and the request stops there. Only a development web request
     * does; production logs it, and the CLI prints it to STDERR.
     */
    public function throwsWarnings(): bool
    {
        return $this->isWeb() && $this->isDevelopment();
    }

    /**
     * Whether the current request asks for a JSON response: an Accept header
     * containing application/json or a +json type, or a JSON Content-Type
     * when no Accept header is sent. Error handlers run outside the router,
     * so this reads the server variables directly.
     */
    public function acceptsJson(): bool
    {
        $server = $this->server ?? $_SERVER;
        $accept = $server['HTTP_ACCEPT'] ?? null;

        if (is_string($accept) && $accept !== '') {
            return $this->isJsonMediaType($accept);
        }

        $contentType = $server['CONTENT_TYPE'] ?? $server['HTTP_CONTENT_TYPE'] ?? '';

        return is_string($contentType) && $this->isJsonMediaType($contentType);
    }

    private function isJsonMediaType(
        string $value,
    ): bool {
        $value = strtolower($value);

        return str_contains($value, 'application/json') || str_contains($value, '+json');
    }

    private function getSapi(): string
    {
        return $this->sapi ?? PHP_SAPI;
    }
}
