<?php

declare(strict_types=1);

use Marko\Roadrunner\Binary\BinaryLocator;
use Marko\Roadrunner\Binary\BinaryLocatorInterface;
use Marko\Roadrunner\Process\ProcessRunner;
use Marko\Roadrunner\Process\ProcessRunnerInterface;

return [
    'bindings' => [
        BinaryLocatorInterface::class => BinaryLocator::class,
        ProcessRunnerInterface::class => ProcessRunner::class,
    ],
];
