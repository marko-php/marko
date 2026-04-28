<?php

declare(strict_types=1);

namespace Marko\CodeIndexer\Contract;

use Marko\CodeIndexer\ValueObject\ConfigKeyEntry;
use Marko\CodeIndexer\ValueObject\ModuleInfo;

interface ConfigScannerInterface
{
    /** @return list<ConfigKeyEntry> */
    public function scan(ModuleInfo $module): array;
}
