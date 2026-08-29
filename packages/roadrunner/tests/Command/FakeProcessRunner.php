<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Command;

use Marko\Roadrunner\Process\ProcessRunnerInterface;

final class FakeProcessRunner implements ProcessRunnerInterface
{
    public ?string $lastCommand = null;

    public function run(string $command): int
    {
        $this->lastCommand = $command;

        return 0;
    }
}
