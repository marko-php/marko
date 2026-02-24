<?php

declare(strict_types=1);

namespace Marko\Scheduler;

use Closure;
use DateTimeInterface;

class ScheduledTask
{
    private string $expression = '* * * * *';

    private ?string $description = null;

    public function __construct(
        private readonly Closure $callback,
    ) {}

    public function everyMinute(): self
    {
        $this->expression = '* * * * *';

        return $this;
    }

    public function everyFiveMinutes(): self
    {
        $this->expression = '*/5 * * * *';

        return $this;
    }

    public function everyTenMinutes(): self
    {
        $this->expression = '*/10 * * * *';

        return $this;
    }

    public function everyFifteenMinutes(): self
    {
        $this->expression = '*/15 * * * *';

        return $this;
    }

    public function everyThirtyMinutes(): self
    {
        $this->expression = '*/30 * * * *';

        return $this;
    }

    public function hourly(): self
    {
        $this->expression = '0 * * * *';

        return $this;
    }

    public function daily(): self
    {
        $this->expression = '0 0 * * *';

        return $this;
    }

    public function weekly(): self
    {
        $this->expression = '0 0 * * 0';

        return $this;
    }

    public function monthly(): self
    {
        $this->expression = '0 0 1 * *';

        return $this;
    }

    public function cron(
        string $expression,
    ): self {
        $this->expression = $expression;

        return $this;
    }

    public function description(
        string $description,
    ): self {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function getCallback(): Closure
    {
        return $this->callback;
    }

    public function isDue(
        DateTimeInterface $now,
    ): bool {
        return CronExpression::matches($this->expression, $now);
    }

    public function run(): mixed
    {
        return ($this->callback)();
    }
}
