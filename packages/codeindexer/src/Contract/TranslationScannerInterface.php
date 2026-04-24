<?php

declare(strict_types=1);

namespace Marko\CodeIndexer\Contract;

use Marko\CodeIndexer\ValueObject\ModuleInfo;
use Marko\CodeIndexer\ValueObject\TranslationEntry;

interface TranslationScannerInterface
{
    /** @return list<TranslationEntry> */
    public function scan(ModuleInfo $module): array;
}
