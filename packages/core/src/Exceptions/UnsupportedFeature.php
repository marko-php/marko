<?php

declare(strict_types=1);

namespace Marko\Core\Exceptions;

/**
 * Describes a capability a driver lacks and builds the strings every
 * "unsupported" exception shares, so the wording is identical everywhere.
 *
 * It only produces strings and assumes no exception parent: each exception
 * maps them onto its own parent constructor.
 */
readonly class UnsupportedFeature
{
    public function __construct(
        public string $feature,
        public string $driver,
        public string $suggestion,
        private ?string $context = null,
        private ?string $message = null,
    ) {}

    public function message(): string
    {
        return $this->message ?? "$this->feature is not supported by the $this->driver driver";
    }

    public function context(): string
    {
        return $this->context ?? "While using $this->feature on the $this->driver driver";
    }
}
