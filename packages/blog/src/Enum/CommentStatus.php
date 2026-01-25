<?php

declare(strict_types=1);

namespace Marko\Blog\Enum;

enum CommentStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
}
