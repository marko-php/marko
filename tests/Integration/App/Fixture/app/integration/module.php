<?php

declare(strict_types=1);

use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\AuthenticationToken\Contracts\TokenRepositoryInterface;
use Marko\AuthenticationToken\Tests\Fixtures\InMemoryTokenRepository;
use Marko\Authorization\Contracts\GateInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Integration\Fixture\Auth\AuthEventLog;
use Marko\Scheduler\Schedule;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeUserProvider;

return [
    'bindings' => [
        // One known user (id 1) for the session guard; the fixture has no
        // users table. FakeUserProvider keeps the user's remember token in
        // memory, so it is shared for the life of the app (#168).
        UserProviderInterface::class => static fn (): UserProviderInterface => new FakeUserProvider(
            users: [1 => new FakeAuthenticatable(id: 1)],
        ),
        // Personal access tokens for the `api` (token driver) guard, kept in
        // memory; the fixture has no personal_access_tokens table (#232).
        TokenRepositoryInterface::class => InMemoryTokenRepository::class,
    ],
    'singletons' => [
        UserProviderInterface::class,
        TokenRepositoryInterface::class,
        AuthEventLog::class,
    ],
    'boot' => static function (Schedule $schedule, GateInterface $gate, ProjectPaths $paths): void {
        // Registered the way the scheduler docs describe; `schedule:run` gets
        // the same shared Schedule, so it finds and runs them (#164).
        $schedule->call(static function () use ($paths): void {
            file_put_contents($paths->base . '/storage/scheduled-task.txt', 'scheduled task ran');
        })->everyMinute()->description('integration heartbeat');

        // Overlap-protected: skipped while a previous run still holds its mutex.
        $schedule->call(static function () use ($paths): void {
            file_put_contents($paths->base . '/storage/exclusive-task.txt', 'exclusive task ran');
        })->everyMinute()->description('integration exclusive')->withoutOverlapping();

        // A console command run in a separate process, holding its overlap mutex
        // there (#565). Only due when a test opts in, since launching needs the
        // project's vendor/bin/marko, which only that test creates.
        $schedule->command('integration:record-background-run', ['--note=ran in the background'])
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground()
            ->when(static fn (): bool => is_file($paths->base . '/storage/run-background-task'));

        $gate->define('view-admin', static fn (): bool => false);
    },
];
