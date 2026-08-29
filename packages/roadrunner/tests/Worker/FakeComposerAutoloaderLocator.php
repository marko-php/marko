<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Worker;

use Marko\Roadrunner\Worker\ComposerAutoloaderLocatorInterface;

readonly class FakeComposerAutoloaderLocator implements ComposerAutoloaderLocatorInterface
{
    public function __construct(
        private ?string $file,
    ) {}

    public function locate(): ?string
    {
        return $this->file;
    }
}
