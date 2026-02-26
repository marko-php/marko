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

    protected function doRun(): void
    {
        EventLoop::run();
    }

    protected function doStop(): void
    {
        EventLoop::getDriver()->stop();
    }
}
