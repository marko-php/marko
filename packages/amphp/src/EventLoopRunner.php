<?php

declare(strict_types=1);

namespace Marko\Amphp;

use Revolt\EventLoop;

class EventLoopRunner
{
    private bool $running = false;

    public function run(): void
    {
        $this->running = true;
        $this->doRun();
        $this->running = false;
    }

    public function stop(): void
    {
        if ($this->running) {
            $this->doStop();
        }

        $this->running = false;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function queue(callable $callback): void
    {
        EventLoop::queue($callback);
    }

    public function onSignal(
        int $signal,
        callable $handler,
    ): void {
        EventLoop::onSignal($signal, function () use ($handler): void {
            $handler();
        });
    }

    public function delay(
        float $seconds,
        callable $callback,
    ): void {
        EventLoop::delay($seconds, function () use ($callback): void {
            $callback();
        });
    }

    protected function doRun(): void
    {
        EventLoop::run();
    }

    protected function doStop(): void
    {
        EventLoop::getDriver()->stop();
    }
}
