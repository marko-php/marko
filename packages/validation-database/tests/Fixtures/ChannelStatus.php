<?php

declare(strict_types=1);

namespace Marko\Validation\Database\Tests\Fixtures;

enum ChannelStatus: string
{
    case Live = 'live';
    case Paused = 'paused';
}
