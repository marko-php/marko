<?php

declare(strict_types=1);

namespace Marko\View\Latte\Tests\Fixtures;

use Marko\View\Attributes\ViewData;
use Marko\View\ViewDataProviderInterface;

#[ViewData]
class SiteDataProvider implements ViewDataProviderInterface
{
    public function data(): array
    {
        return ['siteName' => 'Marko'];
    }
}
