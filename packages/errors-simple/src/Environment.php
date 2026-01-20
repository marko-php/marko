<?php

declare(strict_types=1);

namespace Marko\ErrorsSimple;

class Environment
{
    /**
     * @param array<string, string>|null $envVars
     */
    public function __construct(
        private ?string $sapi = null,
        private ?array $envVars = null,
    ) {}

    public function isCli(): bool
    {
        return $this->getSapi() === 'cli';
    }

    public function isWeb(): bool
    {
        return !$this->isCli();
    }

    public function isDevelopment(): bool
    {
        $env = $this->getEnvVar('MARKO_ENV') ?? $this->getEnvVar('APP_ENV');
        $envLower = $env !== null ? strtolower($env) : null;

        return in_array($envLower, ['dev', 'development', 'local'], true);
    }

    public function isProduction(): bool
    {
        return !$this->isDevelopment();
    }

    private function getSapi(): string
    {
        return $this->sapi ?? PHP_SAPI;
    }

    private function getEnvVar(
        string $name,
    ): ?string {
        if ($this->envVars !== null && array_key_exists($name, $this->envVars)) {
            return $this->envVars[$name];
        }

        $value = getenv($name);

        return $value === false ? null : $value;
    }
}
