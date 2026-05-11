<?php

declare(strict_types=1);

namespace Marko\PageCache\Contracts;

use Marko\PageCache\Attributes\Cacheable;
use Marko\Routing\Http\Request;

interface CacheTagProviderInterface
{
    /** @return array<string> */
    public function tags(Request $request, Cacheable $attribute): array;
}
