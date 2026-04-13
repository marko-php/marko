<?php

declare(strict_types=1);

namespace Marko\Vite\ValueObjects;

use Marko\Vite\Exceptions\EntrypointNotFoundException;

readonly class Manifest
{
    /**
     * @param array<string, ManifestEntry> $entries
     */
    public function __construct(
        public string $path,
        public array $entries,
    ) {}

    /**
     * @throws EntrypointNotFoundException
     */
    public function entry(string $entrypoint): ManifestEntry
    {
        if (!isset($this->entries[$entrypoint])) {
            throw EntrypointNotFoundException::forEntrypoint($entrypoint, $this->path);
        }

        return $this->entries[$entrypoint];
    }
}
