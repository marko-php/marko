<?php

declare(strict_types=1);

namespace Marko\Core\Tests\Unit\Container\Fixtures;

class T016PluggablePlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    public function beforeGetLabel(): ?array
    {
        return null;
    }
}
