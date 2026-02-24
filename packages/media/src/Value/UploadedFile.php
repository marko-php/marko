<?php

declare(strict_types=1);

namespace Marko\Media\Value;

readonly class UploadedFile
{
    public function __construct(
        public string $name,
        public string $tmpPath,
        public string $mimeType,
        public int $size,
        public string $extension,
    ) {}
}
