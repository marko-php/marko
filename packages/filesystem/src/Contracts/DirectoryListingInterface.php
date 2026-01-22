<?php

declare(strict_types=1);

namespace Marko\Filesystem\Contracts;

use IteratorAggregate;
use Marko\Filesystem\Values\DirectoryEntry;

/**
 * @extends IteratorAggregate<int, DirectoryEntry>
 */
interface DirectoryListingInterface extends IteratorAggregate
{
    /**
     * @return array<DirectoryEntry>
     */
    public function entries(): array;

    /**
     * @return array<DirectoryEntry>
     */
    public function files(): array;

    /**
     * @return array<DirectoryEntry>
     */
    public function directories(): array;
}
