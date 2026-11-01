<?php

declare(strict_types=1);

namespace Marko\View\Tests\Fixtures;

use Marko\View\Attributes\ViewData;
use Marko\View\ViewDataProviderInterface;

#[ViewData]
class SiteDataProvider implements ViewDataProviderInterface
{
    public int $calls = 0;

    public function data(): array
    {
        $this->calls++;

        return [
            'siteName' => 'Marko',
            'tagline' => 'Modular PHP',
        ];
    }
}
