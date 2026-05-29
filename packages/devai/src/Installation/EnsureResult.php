<?php

declare(strict_types=1);

namespace Marko\DevAi\Installation;

readonly class EnsureResult
{
    private const string ALREADY_INSTALLED = 'alreadyInstalled';

    private const string INSTALLED = 'installed';

    private const string SKIPPED = 'skipped';

    private function __construct(private string $status) {}

    public static function alreadyInstalled(): self
    {
        return new self(self::ALREADY_INSTALLED);
    }

    public static function installed(): self
    {
        return new self(self::INSTALLED);
    }

    public static function skipped(): self
    {
        return new self(self::SKIPPED);
    }

    public function isAlreadyInstalled(): bool
    {
        return $this->status === self::ALREADY_INSTALLED;
    }

    public function isInstalled(): bool
    {
        return $this->status === self::INSTALLED;
    }

    public function isSkipped(): bool
    {
        return $this->status === self::SKIPPED;
    }
}
