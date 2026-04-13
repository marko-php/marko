<?php

declare(strict_types=1);

namespace Marko\Vite\Contracts;

use Marko\Vite\ValueObjects\Manifest;
use Marko\Vite\ValueObjects\ManifestEntry;

interface ManifestRepositoryInterface
{
    public function manifest(): Manifest;

    public function entry(string $entrypoint): ManifestEntry;
}
