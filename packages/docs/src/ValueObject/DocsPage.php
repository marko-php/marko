<?php

declare(strict_types=1);

namespace Marko\Docs\ValueObject;

readonly class DocsPage
{
    public function __construct(
        public string $id,
        public string $title,
        public string $content,
        public string $path,
    ) {}
}
