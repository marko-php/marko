<?php

declare(strict_types=1);

use Marko\Core\Command\CommandDefinition;
use Marko\Core\Command\CommandRegistry;
use Marko\Scheduler\Exceptions\SchedulerException;
use Marko\Scheduler\ScheduledCommand;
use Marko\Scheduler\ScheduledTask;
use Marko\Scheduler\Tests\Fixtures\SchedulerFactory;

beforeEach(function (): void {
    [$registry] = SchedulerFactory::commands([
        'app:prune' => fn (): int => 0,
    ]);
    $this->schedule = SchedulerFactory::schedule($registry);
});

it('registers a command task with its name and arguments', function (): void {
    $task = $this->schedule->command('app:prune', ['--days=30', 'users']);

    expect($task)->toBeInstanceOf(ScheduledTask::class)
        ->and($this->schedule->tasks())->toBe([$task])
        ->and($task->getCommand())->toBeInstanceOf(ScheduledCommand::class)
        ->and($task->getCommand()->name)->toBe('app:prune')
        ->and($task->getCommand()->arguments)->toBe(['--days=30', 'users']);
});

it('accepts a command registered under an alias', function (): void {
    $registry = new CommandRegistry();
    $registry->register(new CommandDefinition(commandClass: 'App\\Prune', name: 'app:prune', aliases: ['prune']));

    $task = SchedulerFactory::schedule($registry)->command('prune');

    expect($task->getCommand()->name)->toBe('prune');
});

it('throws when the schedule is built with an unknown command', function (): void {
    expect(fn () => $this->schedule->command('app:does-not-exist'))
        ->toThrow(
            SchedulerException::class,
            "Schedule::command() was given 'app:does-not-exist', which is not a registered command.",
        );

    expect($this->schedule->tasks())->toBe([]);
});

it('throws when a command argument is not a string', function (): void {
    expect(fn () => $this->schedule->command('app:prune', ['--days', 30]))
        ->toThrow(SchedulerException::class, "Scheduled command 'app:prune' was given a int argument");
});

it('describes a command task by its command name and arguments by default', function (): void {
    expect($this->schedule->command('app:prune')->getDescription())->toBe('app:prune')
        ->and($this->schedule->command('app:prune', ['--days=30', 'users'])->getDescription())
        ->toBe('app:prune --days=30 users');
});

it('uses an explicit description instead of the default one', function (): void {
    $task = $this->schedule->command('app:prune', ['--days=30'])->description('Prune old rows');

    expect($task->getDescription())->toBe('Prune old rows');
});

it('builds an overlap mutex name for a command task without a description', function (): void {
    $task = $this->schedule->command('app:prune', ['--days=30'])->everyMinute()->withoutOverlapping();

    expect($task->mutexName())->toBe('schedule-' . sha1('* * * * *app:prune --days=30'));
});

it('has no command for a closure task', function (): void {
    expect($this->schedule->call(fn (): null => null)->getCommand())->toBeNull();
});

it('refuses to hand out a callback for a command task', function (): void {
    $task = $this->schedule->command('app:prune');

    expect(fn () => $task->getCallback())
        ->toThrow(
            SchedulerException::class,
            "getCallback() was called on scheduled task 'app:prune', which runs a command, not a closure.",
        )
        ->and(fn () => $task->run())
        ->toThrow(SchedulerException::class, 'which runs a command, not a closure');
});

it('marks a command task to run in the background', function (): void {
    $task = $this->schedule->command('app:prune');

    expect($task->runsInBackground())->toBeFalse()
        ->and($task->runInBackground())->toBe($task)
        ->and($task->runsInBackground())->toBeTrue();
});

it('throws when runInBackground() is called on a closure task', function (): void {
    $task = $this->schedule->call(fn (): null => null)->description('Send digest');

    try {
        $task->runInBackground();
        $this->fail('runInBackground() accepted a closure task.');
    } catch (SchedulerException $e) {
        expect($e->getMessage())->toBe("Scheduled task 'Send digest' is a closure and cannot runInBackground().")
            ->and($e->getSuggestion())->toContain('#[Command]');
    }

    expect($task->runsInBackground())->toBeFalse();
});

it('builds the Input a scheduled command receives', function (): void {
    $input = new ScheduledCommand('app:prune', ['--days=30', 'users'])->input();

    expect($input->getCommand())->toBe('app:prune')
        ->and($input->getOption('days'))->toBe('30')
        ->and($input->getArguments())->toBe(['users']);
});
