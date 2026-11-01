<?php

declare(strict_types=1);

namespace Marko\Scheduler\Tests\Fixtures;

use Closure;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

/**
 * A command whose behaviour a test supplies: $handler receives the Input and Output and returns the exit code.
 */
readonly class RecordingCommand implements CommandInterface
{
    /**
     * @param Closure(Input, Output): int $handler
     */
    public function __construct(
        private Closure $handler,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        return ($this->handler)($input, $output);
    }
}
