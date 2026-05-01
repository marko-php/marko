<?php

declare(strict_types=1);

namespace Marko\CodeIndexer\Contract;

use Marko\CodeIndexer\ValueObject\ModuleInfo;
use Marko\CodeIndexer\ValueObject\TemplateEntry;

interface TemplateScannerInterface
{
    /** @return list<TemplateEntry> */
    public function scan(ModuleInfo $module): array;
}
