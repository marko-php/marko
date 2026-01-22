<?php

declare(strict_types=1);

namespace Marko\Filesystem\Values;

use ArrayIterator;
use Marko\Filesystem\Contracts\DirectoryListingInterface;
use Traversable;

readonly class DirectoryListing implements DirectoryListingInterface
{
    /**
     * @param array<DirectoryEntry> $entries
     */
    public function __construct(
        private array $entries,
    ) {}

    /**
     * @return array<DirectoryEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @return array<DirectoryEntry>
     */
    public function files(): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn (DirectoryEntry $entry): bool => $entry->isFile(),
        ));
    }

    /**
     * @return array<DirectoryEntry>
     */
    public function directories(): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn (DirectoryEntry $entry): bool => $entry->isDirectory,
        ));
    }

    /**
     * @return Traversable<int, DirectoryEntry>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->entries);
    }
}
