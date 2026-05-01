<?php

declare(strict_types=1);

namespace Marko\DevAi\Contract;

use Marko\DevAi\ValueObject\SkillBundle;

interface SupportsSkills
{
    /**
     * @param list<SkillBundle> $bundles current skill bundles to write
     * @param list<string> $previouslyShipped skill names previously shipped by devai
     *                                        (used to remove orphans without clobbering user-authored skills)
     */
    public function distributeSkills(
        array $bundles,
        string $projectRoot,
        array $previouslyShipped = [],
    ): void;
}
