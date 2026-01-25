<?php

declare(strict_types=1);

namespace Marko\Blog\Services;

use Closure;

interface SlugGeneratorInterface
{
    /**
     * @param Closure(string): bool|null $uniquenessChecker
     */
    public function generate(
        string $title,
        ?Closure $uniquenessChecker = null,
    ): string;
}
