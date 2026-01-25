<?php

declare(strict_types=1);

namespace Marko\Blog\Enum;

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Scheduled = 'scheduled';
}
