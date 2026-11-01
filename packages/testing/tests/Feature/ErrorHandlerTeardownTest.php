<?php

declare(strict_types=1);

use Marko\Errors\Contracts\ErrorHandlerInterface;
use Marko\Testing\BootedApplications;
use Marko\Testing\Database\TestDatabase;
use Marko\Testing\Exceptions\DatabaseTestException;
use Marko\Testing\Http\TestClient;
use Marko\Testing\TestCase;

use function Marko\Testing\Tests\errorsAppPath;
use function Marko\Testing\Tests\globalHandlerStacks;
use function Marko\Testing\Tests\withAppEnv;

/**
 * The errors-app fixture boots the real marko/errors-simple wiring, which pushes a global
 * error handler and exception handler. Every test reads PHP's handler stacks before it
 * boots and checks they are exactly the same after the teardown marko/testing runs.
 */
describe('error handler teardown after a marko/testing boot', function (): void {
    afterEach(function (): void {
        BootedApplications::shutdownAll();
    });

    it('installs the app error handlers on boot, so the fixture exercises the teardown', function (): void {
        $before = globalHandlerStacks();

        $client = TestClient::boot(errorsAppPath());
        $handler = $client->application()->container->get(ErrorHandlerInterface::class);
        $booted = globalHandlerStacks();

        expect(count($booted['errorHandlers']))->toBe(count($before['errorHandlers']) + 1)
            ->and(count($booted['exceptionHandlers']))->toBe(count($before['exceptionHandlers']) + 1)
            ->and(array_last($booted['errorHandlers']))->toBe([$handler, 'handleError'])
            ->and(array_last($booted['exceptionHandlers']))->toBe([$handler, 'handleException']);
    });

    it('leaves the error and exception handler stacks as it found them after TestClient::boot()', function (): void {
        $before = globalHandlerStacks();

        TestClient::boot(errorsAppPath());
        BootedApplications::shutdownAll();

        expect(globalHandlerStacks())->toBe($before);
    });

    it('does not stack handlers across repeated boots', function (): void {
        $before = globalHandlerStacks();

        foreach (range(1, 3) as $ignored) {
            TestClient::boot(errorsAppPath());
            BootedApplications::shutdownAll();

            expect(globalHandlerStacks())->toBe($before);
        }
    });

    it('removes the handlers of every client booted in one test', function (): void {
        $before = globalHandlerStacks();

        TestClient::boot(errorsAppPath());
        TestClient::boot(errorsAppPath());
        TestClient::boot(errorsAppPath());
        BootedApplications::shutdownAll();

        expect(globalHandlerStacks())->toBe($before);
    });

    it('leaves the handler stacks as it found them after a refused TestDatabase::boot()', function (): void {
        $before = globalHandlerStacks();

        // Boots (installing the handlers), then refuses: the fixture has no database driver
        expect(fn () => withAppEnv('testing', fn () => TestDatabase::boot(errorsAppPath())))
            ->toThrow(DatabaseTestException::class, 'No database driver is installed');
        BootedApplications::shutdownAll();

        expect(globalHandlerStacks())->toBe($before);
    });

    it('removes the handlers from the TestCase tearDown', function (): void {
        $before = globalHandlerStacks();
        $testCase = new class ('test') extends TestCase
        {
            public function tearDown(): void
            {
                parent::tearDown();
            }
        };

        TestClient::boot(errorsAppPath());
        $testCase->tearDown();

        expect(globalHandlerStacks())->toBe($before);
    });

});
