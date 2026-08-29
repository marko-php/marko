<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Command;

use Marko\Roadrunner\Binary\BinaryLocatorInterface;

final readonly class FakeBinaryLocator implements BinaryLocatorInterface
{
    public function __construct(
        private ?string $path,
    ) {}

    public function locate(): ?string
    {
        return $this->path;
    }
}
