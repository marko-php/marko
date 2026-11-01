<?php

declare(strict_types=1);

namespace Marko\Integration\Fixture\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Path\ProjectPaths;

/**
 * Scheduled with runInBackground() by the fixture module: writes its --note and
 * process id to storage/background-command.txt so a test can see that a separate
 * process ran it to completion (#565).
 */
#[Command(name: 'integration:record-background-run', description: 'Record a background scheduled run')]
readonly class RecordBackgroundRunCommand implements CommandInterface
{
    public function __construct(
        private ProjectPaths $paths,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $file = $this->paths->base . '/storage/background-command.txt';
        file_put_contents("$file.tmp", json_encode([
            'note' => $input->getOption('note'),
            'pid' => getmypid(),
        ]));
        rename("$file.tmp", $file);

        return 0;
    }
}
