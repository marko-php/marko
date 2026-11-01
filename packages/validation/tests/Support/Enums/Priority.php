<?php

declare(strict_types=1);

namespace Marko\Validation\Tests\Support\Enums;

enum Priority: int
{
    case Low = 1;
    case High = 2;
}
