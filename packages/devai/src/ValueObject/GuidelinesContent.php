<?php

declare(strict_types=1);

namespace Marko\DevAi\ValueObject;

readonly class GuidelinesContent
{
    public function __construct(
        public string $body,
        public string $filename = 'AGENTS.md',
    ) {}
}
