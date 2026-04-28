<?php

declare(strict_types=1);

namespace Marko\CodeIndexer\ValueObject;

readonly class TemplateEntry
{
    public function __construct(
        public string $moduleName,
        public string $templateName,
        public string $absolutePath,
        public string $extension,
    ) {}
}
