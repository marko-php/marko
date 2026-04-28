<?php

declare(strict_types=1);

use Marko\DevAi\Process\CommandRunner;
use Marko\DevAi\Process\CommandRunnerInterface;

return [
    'bindings' => [
        CommandRunnerInterface::class => CommandRunner::class,
    ],
    'singletons' => [],
];
