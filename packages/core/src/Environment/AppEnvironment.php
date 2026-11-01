<?php

declare(strict_types=1);

namespace Marko\Core\Environment;

/**
 * The single answer to "which environment is this application running in?".
 *
 * Reads MARKO_ENV, then APP_ENV, through EnvironmentVariables ($_ENV with a
 * getenv() fallback). When neither is set the environment is "production",
 * so a misconfigured deployment fails safe rather than exposing development
 * behaviour.
 */
class AppEnvironment
{
    public const string DEFAULT_NAME = 'production';

    /** @var list<string> */
    public const array PRODUCTION_NAMES = ['production', 'prod'];

    /** @var list<string> */
    public const array DEVELOPMENT_NAMES = ['development', 'dev', 'local'];

    /** @var list<string> */
    public const array TESTING_NAMES = ['testing', 'test'];

    private const array VARIABLE_NAMES = ['MARKO_ENV', 'APP_ENV'];

    /**
     * @param array<string, string>|null $variables Explicit variables to read instead of the real environment
     */
    public function __construct(
        private readonly ?array $variables = null,
    ) {}

    /**
     * The environment name, trimmed and lowercased, or "production" when unset.
     */
    public function name(): string
    {
        foreach (self::VARIABLE_NAMES as $variable) {
            $value = strtolower(trim($this->read($variable) ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return self::DEFAULT_NAME;
    }

    public function isProduction(): bool
    {
        return in_array($this->name(), self::PRODUCTION_NAMES, true);
    }

    public function isDevelopment(): bool
    {
        return in_array($this->name(), self::DEVELOPMENT_NAMES, true);
    }

    public function isTesting(): bool
    {
        return in_array($this->name(), self::TESTING_NAMES, true);
    }

    private function read(
        string $name,
    ): ?string {
        if ($this->variables !== null) {
            return $this->variables[$name] ?? null;
        }

        return EnvironmentVariables::read($name);
    }
}
