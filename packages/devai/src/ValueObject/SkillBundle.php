<?php

declare(strict_types=1);

namespace Marko\DevAi\ValueObject;

readonly class SkillBundle
{
    /** @param array<string, string> $skills filename => content */
    public function __construct(
        public string $bundleName,
        public array $skills,
    ) {}
}
