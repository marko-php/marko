<?php

declare(strict_types=1);

namespace Marko\DevAi\Contract;

use Marko\DevAi\ValueObject\SkillBundle;

interface SupportsSkills
{
    /** @param list<SkillBundle> $bundles */
    public function distributeSkills(array $bundles, string $projectRoot): void;
}
