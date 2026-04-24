<?php

declare(strict_types=1);

namespace Marko\CodeIndexer\Contract;

use Marko\CodeIndexer\ValueObject\ModuleInfo;

interface ModuleWalkerInterface
{
    /** @return list<ModuleInfo> */
    public function walk(): array;
}
