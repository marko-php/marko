<?php

declare(strict_types=1);

namespace Marko\TailwindCss;

use Marko\Config\ConfigRepositoryInterface;
use Marko\TailwindCss\Contracts\ContentPathProviderInterface;

class DefaultContentPathProvider implements ContentPathProviderInterface
{
    public function __construct(
        private readonly ConfigRepositoryInterface $config,
    ) {}

    public function contentPaths(): array
    {
        if (!$this->config->getBool('tailwindcss.enabled')) {
            return [];
        }

        return array_values(array_map(
            'strval',
            $this->config->getArray('tailwindcss.content_paths'),
        ));
    }
}
