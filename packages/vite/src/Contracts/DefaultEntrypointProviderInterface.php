<?php

declare(strict_types=1);

namespace Marko\Vite\Contracts;

interface DefaultEntrypointProviderInterface
{
    /**
     * @return array<string>
     */
    public function entrypoints(): array;
}
