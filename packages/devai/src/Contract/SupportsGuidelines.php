<?php

declare(strict_types=1);

namespace Marko\DevAi\Contract;

use Marko\DevAi\ValueObject\GuidelinesContent;

interface SupportsGuidelines
{
    public function writeGuidelines(GuidelinesContent $content, string $projectRoot): void;
}
