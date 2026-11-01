<?php

declare(strict_types=1);

use Marko\Core\Application;
use Marko\Testing\BootedApplications;

/**
 * An application that records its shutdown() calls in $log instead of booting anything.
 *
 * @param ArrayObject<int, string> $log
 */
function recordingApplication(
    string $name,
    ArrayObject $log,
    ?Throwable $throws = null,
): Application {
    return new class ($name, $log, $throws) extends Application
    {
        /**
         * @param ArrayObject<int, string> $log
         */
        public function __construct(
            private readonly string $name,
            private readonly ArrayObject $log,
            private readonly ?Throwable $throws,
        ) {
            parent::__construct();
        }

        public function shutdown(): void
        {
            $this->log[] = $this->name;

            if ($this->throws !== null) {
                throw $this->throws;
            }
        }
    };
}

describe('BootedApplications', function (): void {
    afterEach(function (): void {
        BootedApplications::shutdownAll();
    });

    it('shuts down every tracked application, the most recently booted first', function (): void {
        $log = new ArrayObject();
        BootedApplications::track(recordingApplication('first', $log));
        BootedApplications::track(recordingApplication('second', $log));

        BootedApplications::shutdownAll();

        expect($log->getArrayCopy())->toBe(['second', 'first']);
    });

    it('forgets an application once it has been shut down', function (): void {
        $log = new ArrayObject();
        BootedApplications::track(recordingApplication('only', $log));

        BootedApplications::shutdownAll();
        BootedApplications::shutdownAll();

        expect($log->getArrayCopy())->toBe(['only']);
    });

    it('tracks the same application once however often it is tracked', function (): void {
        $log = new ArrayObject();
        $application = recordingApplication('shared', $log);
        BootedApplications::track($application);
        BootedApplications::track($application);

        BootedApplications::shutdownAll();

        expect($log->getArrayCopy())->toBe(['shared']);
    });

    it('still shuts down the others when one throws, then rethrows the first failure', function (): void {
        $log = new ArrayObject();
        BootedApplications::track(recordingApplication('first', $log, new RuntimeException('first failed')));
        BootedApplications::track(recordingApplication('second', $log, new LogicException('second failed')));
        BootedApplications::track(recordingApplication('third', $log));

        expect(fn () => BootedApplications::shutdownAll())->toThrow(LogicException::class, 'second failed')
            ->and($log->getArrayCopy())->toBe(['third', 'second', 'first']);
    });
});
