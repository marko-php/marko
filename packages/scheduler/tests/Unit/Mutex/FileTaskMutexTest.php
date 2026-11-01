<?php

declare(strict_types=1);

use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Module\ManifestParser;
use Marko\Core\Path\ProjectPaths;
use Marko\Scheduler\Mutex\FileTaskMutex;
use Marko\Scheduler\Mutex\TaskMutexInterface;
use Marko\Scheduler\ScheduledTask;
use Marko\Testing\Fake\FakeClock;
use Psr\Clock\ClockInterface;

beforeEach(function (): void {
    $this->basePath = sys_get_temp_dir() . '/marko-scheduler-mutex-' . bin2hex(random_bytes(6));
    $this->directory = $this->basePath . '/storage/framework';
    $this->clock = new FakeClock('@1700000000');
    $this->task = new ScheduledTask(fn (): null => null)
        ->everyMinute()
        ->description('Import feed')
        ->withoutOverlapping();
});

afterEach(function (): void {
    if (is_dir($this->basePath)) {
        exec('rm -rf ' . escapeshellarg($this->basePath));
    }
});

it('implements TaskMutexInterface', function (): void {
    expect(new FileTaskMutex($this->directory, $this->clock))->toBeInstanceOf(TaskMutexInterface::class);
});

it('acquires the mutex when no other holder exists', function (): void {
    $mutex = new FileTaskMutex($this->directory, $this->clock);

    expect($mutex->acquire($this->task->mutexName(), 60))->toBeTrue();
});

it('refuses to acquire a mutex held by another holder', function (): void {
    $holder = new FileTaskMutex($this->directory, $this->clock);
    $contender = new FileTaskMutex($this->directory, $this->clock);

    $holder->acquire($this->task->mutexName(), 60);

    expect($contender->acquire($this->task->mutexName(), 60))->toBeFalse();
});

it('refuses to acquire the mutex twice through the same instance', function (): void {
    $mutex = new FileTaskMutex($this->directory, $this->clock);

    $mutex->acquire($this->task->mutexName(), 60);

    expect($mutex->acquire($this->task->mutexName(), 60))->toBeFalse();
});

it('reclaims a mutex whose holder is past its expiry', function (): void {
    $holder = new FileTaskMutex($this->directory, $this->clock);
    $contender = new FileTaskMutex($this->directory, $this->clock);
    $third = new FileTaskMutex($this->directory, $this->clock);

    $holder->acquire($this->task->mutexName(), 60);
    $this->clock->travel('+59 seconds');
    $beforeExpiry = $contender->acquire($this->task->mutexName(), 60);
    $this->clock->travel('+2 seconds');
    $afterExpiry = $contender->acquire($this->task->mutexName(), 60);

    expect($beforeExpiry)->toBeFalse()
        ->and($afterExpiry)->toBeTrue()
        ->and($third->acquire($this->task->mutexName(), 60))->toBeFalse();
});

it('writes the mutex expiry from the injected clock', function (): void {
    $mutex = new FileTaskMutex($this->directory, $this->clock);

    $mutex->acquire($this->task->mutexName(), 90);

    expect(file_get_contents($this->directory . '/' . $this->task->mutexName()))->toBe('1700000090');
});

it('treats a held mutex as stale once the injected clock passes its expiry', function (): void {
    $holder = new FileTaskMutex($this->directory, $this->clock);
    $observer = new FileTaskMutex($this->directory, $this->clock);

    $holder->acquire($this->task->mutexName(), 60);
    $this->clock->travel('+59 seconds');
    $beforeExpiry = $observer->exists($this->task->mutexName());
    $this->clock->travel('+1 second');
    $atExpiry = $observer->exists($this->task->mutexName());

    expect($beforeExpiry)->toBeTrue()
        ->and($atExpiry)->toBeFalse();
});

it('allows acquiring again after release', function (): void {
    $holder = new FileTaskMutex($this->directory, $this->clock);
    $contender = new FileTaskMutex($this->directory, $this->clock);

    $holder->acquire($this->task->mutexName(), 60);
    $holder->release($this->task->mutexName());

    expect($contender->acquire($this->task->mutexName(), 60))->toBeTrue();
});

it('reports whether the mutex exists', function (): void {
    $holder = new FileTaskMutex($this->directory, $this->clock);
    $observer = new FileTaskMutex($this->directory, $this->clock);

    $beforeAcquire = $observer->exists($this->task->mutexName());
    $holder->acquire($this->task->mutexName(), 60);
    $whileHeld = $observer->exists($this->task->mutexName());
    $this->clock->travel('+61 seconds');
    $afterExpiry = $observer->exists($this->task->mutexName());
    $holder->release($this->task->mutexName());
    $afterRelease = $observer->exists($this->task->mutexName());

    expect($beforeAcquire)->toBeFalse()
        ->and($whileHeld)->toBeTrue()
        ->and($afterExpiry)->toBeFalse()
        ->and($afterRelease)->toBeFalse();
});

it('creates the mutex directory when missing', function (): void {
    $mutex = new FileTaskMutex($this->directory, $this->clock);

    $mutex->acquire($this->task->mutexName(), 60);

    expect(is_file($this->directory . '/' . $this->task->mutexName()))->toBeTrue();
});

it('binds TaskMutexInterface to FileTaskMutex under storage/framework in module.php', function (): void {
    $container = new Container();
    $container->instance(ProjectPaths::class, new ProjectPaths($this->basePath));
    $container->instance(ClockInterface::class, $this->clock);
    new BindingRegistry($container)->registerModule(new ManifestParser()->parse(dirname(__DIR__, 3)));

    $mutex = $container->get(TaskMutexInterface::class);
    $mutex->acquire($this->task->mutexName(), 60);

    expect($mutex)->toBeInstanceOf(FileTaskMutex::class)
        ->and(is_file($this->basePath . '/storage/framework/' . $this->task->mutexName()))->toBeTrue();
});
