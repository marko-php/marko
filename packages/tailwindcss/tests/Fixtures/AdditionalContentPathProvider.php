<?php

declare(strict_types=1);

namespace Marko\TailwindCss\Tests\Fixtures;

use Marko\TailwindCss\Contracts\ContentPathProviderInterface;

class AdditionalContentPathProvider implements ContentPathProviderInterface
{
    public function contentPaths(): array
    {
        return [
            'modules/inertiajs/resources/js/**/*.{jsx,tsx,vue,svelte}',
            'resources/views/**/*.latte',
        ];
    }
}
