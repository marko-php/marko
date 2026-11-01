<?php

declare(strict_types=1);

namespace Marko\View\Tests\Fixtures;

use Marko\View\Attributes\ViewData;
use Marko\View\ViewDataProviderInterface;

#[ViewData]
class ConflictingDataProvider implements ViewDataProviderInterface
{
    public function data(): array
    {
        return [
            'siteName' => 'Other',
        ];
    }
}
