<?php

declare(strict_types=1);

namespace Marko\PageCache\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Marko\PageCache\Contracts\CacheTagProviderInterface;

class PageCacheException extends MarkoException
{
    public static function missingEntityBridge(string $offendingClass): self
    {
        return new self(
            message: "Class '$offendingClass' implements IdentityInterface but marko/page-cache-entity is not installed. The page cache will not be invalidated when this entity changes.",
            context: 'Detected during marko/page-cache boot validation',
            suggestion: 'Install the bridge package: composer require marko/page-cache-entity',
        );
    }

    public static function invalidTagProvider(string $providerClass): self
    {
        return new self(
            message: "Class '$providerClass' does not implement CacheTagProviderInterface.",
            context: 'Resolved from the container as the tag provider for a #[Cacheable] attribute.',
            suggestion: 'Implement ' . CacheTagProviderInterface::class . " in '$providerClass'.",
        );
    }
}
