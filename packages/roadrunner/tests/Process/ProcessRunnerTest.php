<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Process;

use Marko\Roadrunner\Process\ProcessRunner;

describe('ProcessRunner', function (): void {
    it('returns the exit code of the command it runs', function (): void {
        $runner = new ProcessRunner();

        expect($runner->run('true'))->toBe(0)
            ->and($runner->run('false'))->toBe(1);
    });
});
