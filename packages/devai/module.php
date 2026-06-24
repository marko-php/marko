<?php

declare(strict_types=1);

use Marko\DevAi\Process\CommandRunner;
use Marko\DevAi\Process\CommandRunnerInterface;
use Marko\DevAi\Process\ConfirmationPrompterInterface;
use Marko\DevAi\Process\StdinPrompter;

return [
    'bindings' => [
        CommandRunnerInterface::class => CommandRunner::class,
        ConfirmationPrompterInterface::class => StdinPrompter::class,
    ],
    'singletons' => [],
];
