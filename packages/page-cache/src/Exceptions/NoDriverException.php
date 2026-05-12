<?php

declare(strict_types=1);

namespace Marko\PageCache\Exceptions;

class NoDriverException extends PageCacheException
{
    public static function noBinding(): self
    {
        return new self(
            message: 'No page cache driver installed.',
            context: 'Attempted to resolve a page cache interface but no implementation is bound.',
            suggestion: 'Install a page cache driver: `composer require marko/page-cache-file` or another driver',
        );
    }
}
