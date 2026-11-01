<?php

declare(strict_types=1);

namespace Marko\Scheduler\Tests\Fixtures;

use Marko\Scheduler\Process\BackgroundCommandLauncherInterface;
use Throwable;

/**
 * Records every launch instead of starting a process. Throws $failWith for the commands named in $failFor.
 */
class RecordingBackgroundCommandLauncher implements BackgroundCommandLauncherInterface
{
    /** @var list<array{command: string, arguments: list<string>, mutexName: ?string}> */
    public array $launches = [];

    /**
     * @param list<string> $failFor
     */
    public function __construct(
        private readonly array $failFor = [],
        private readonly ?Throwable $failWith = null,
    ) {}

    /**
     * @throws Throwable
     */
    public function launch(
        string $command,
        array $arguments,
        ?string $mutexName,
    ): void {
        if ($this->failWith !== null && in_array($command, $this->failFor, true)) {
            throw $this->failWith;
        }

        $this->launches[] = ['command' => $command, 'arguments' => $arguments, 'mutexName' => $mutexName];
    }
}
