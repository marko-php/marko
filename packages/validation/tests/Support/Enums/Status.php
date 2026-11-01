<?php

declare(strict_types=1);

namespace Marko\Validation\Tests\Support\Enums;

enum Status: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
