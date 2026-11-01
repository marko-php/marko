<?php

declare(strict_types=1);

namespace Marko\Scheduler;

use Closure;
use DateInvalidTimeZoneException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Marko\Scheduler\Exceptions\SchedulerException;

class ScheduledTask
{
    private string $expression = '* * * * *';

    private ?string $description = null;

    private ?int $overlapExpiresAfterMinutes = null;

    private ?DateTimeZone $timezone = null;

    /** @var array<Closure> */
    private array $whenConditions = [];

    /** @var array<Closure> */
    private array $skipConditions = [];

    private bool $runsInBackground = false;

    /**
     * @param Closure|ScheduledCommand $action The closure to call, or the console command to run
     */
    public function __construct(
        private readonly Closure|ScheduledCommand $action,
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

    /**
     * Run once a day at a 24-hour HH:MM time, e.g. dailyAt('03:30').
     *
     * @throws SchedulerException When the time is malformed or out of range
     */
    public function dailyAt(
        string $time,
    ): self {
        [$hour, $minute] = $this->parseTime('dailyAt', $time);
        $this->expression = "$minute $hour * * *";

        return $this;
    }

    /**
     * Alias of dailyAt().
     *
     * @throws SchedulerException When the time is malformed or out of range
     */
    public function at(
        string $time,
    ): self {
        [$hour, $minute] = $this->parseTime('at', $time);
        $this->expression = "$minute $hour * * *";

        return $this;
    }

    /**
     * Run twice a day, at $firstHour and $secondHour, both at $minute past the hour.
     *
     * @throws SchedulerException When an hour or the minute is out of range, or both hours are the same
     */
    public function twiceDaily(
        int $firstHour,
        int $secondHour,
        int $minute = 0,
    ): self {
        $this->assertHour('twiceDaily', $firstHour);
        $this->assertHour('twiceDaily', $secondHour);
        $this->assertMinute('twiceDaily', $minute);

        if ($firstHour === $secondHour) {
            throw SchedulerException::duplicateHour('twiceDaily', $firstHour);
        }

        $this->expression = "$minute $firstHour,$secondHour * * *";

        return $this;
    }

    /**
     * Run once a week on $dayOfWeek (0 = Sunday ... 6 = Saturday; 7 is also Sunday) at a 24-hour HH:MM time.
     *
     * @throws SchedulerException When the day of week or the time is invalid
     */
    public function weeklyOn(
        int $dayOfWeek,
        string $time,
    ): self {
        if ($dayOfWeek < 0 || $dayOfWeek > 7) {
            throw SchedulerException::invalidDayOfWeek('weeklyOn', $dayOfWeek);
        }

        [$hour, $minute] = $this->parseTime('weeklyOn', $time);
        $dayOfWeek %= 7;
        $this->expression = "$minute $hour * * $dayOfWeek";

        return $this;
    }

    /**
     * Run once a month on $dayOfMonth (1-31) at a 24-hour HH:MM time. Months without that day are skipped.
     *
     * @throws SchedulerException When the day of month or the time is invalid
     */
    public function monthlyOn(
        int $dayOfMonth,
        string $time,
    ): self {
        if ($dayOfMonth < 1 || $dayOfMonth > 31) {
            throw SchedulerException::invalidDayOfMonth('monthlyOn', $dayOfMonth);
        }

        [$hour, $minute] = $this->parseTime('monthlyOn', $time);
        $this->expression = "$minute $hour $dayOfMonth * *";

        return $this;
    }

    /**
     * Match the schedule against wall-clock time in this zone instead of the zone of the time it is checked at.
     *
     * @throws SchedulerException When the zone name is unknown
     */
    public function timezone(
        DateTimeZone|string $timezone,
    ): self {
        if (is_string($timezone)) {
            try {
                $timezone = new DateTimeZone($timezone);
            } catch (DateInvalidTimeZoneException) {
                throw SchedulerException::unknownTimezone($timezone);
            }
        }

        $this->timezone = $timezone;

        return $this;
    }

    public function getTimezone(): ?DateTimeZone
    {
        return $this->timezone;
    }

    /**
     * Only run when $condition returns true. Every when() and skip() condition must allow the run.
     */
    public function when(
        Closure $condition,
    ): self {
        $this->whenConditions[] = $condition;

        return $this;
    }

    /**
     * Do not run when $condition returns true. Every when() and skip() condition must allow the run.
     */
    public function skip(
        Closure $condition,
    ): self {
        $this->skipConditions[] = $condition;

        return $this;
    }

    /**
     * Evaluate the when() and skip() conditions now.
     *
     * An exception thrown by a condition propagates to the caller.
     *
     * @throws SchedulerException When a condition returns something other than a bool
     */
    public function passesConditions(): bool
    {
        foreach ($this->whenConditions as $condition) {
            if (!$this->evaluateCondition('when', $condition)) {
                return false;
            }
        }

        foreach ($this->skipConditions as $condition) {
            if ($this->evaluateCondition('skip', $condition)) {
                return false;
            }
        }

        return true;
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

    /**
     * The description set with description(). A command task without one is described by its
     * command name and arguments, e.g. 'app:prune --days=30'.
     */
    public function getDescription(): ?string
    {
        if ($this->description === null && $this->action instanceof ScheduledCommand) {
            return $this->action->description();
        }

        return $this->description;
    }

    /**
     * Skip this task while a previous run is still in progress.
     *
     * The mutex is keyed on the description, so a closure task must also have a description();
     * a command task defaults to its command name and arguments. A held mutex older than
     * $expiresAfterMinutes is treated as stale (its holder hung or crashed) and is reclaimed.
     *
     * @throws SchedulerException When $expiresAfterMinutes is below 1
     */
    public function withoutOverlapping(
        int $expiresAfterMinutes = 1440,
    ): self {
        if ($expiresAfterMinutes < 1) {
            throw SchedulerException::invalidOverlapExpiry($expiresAfterMinutes);
        }

        $this->overlapExpiresAfterMinutes = $expiresAfterMinutes;

        return $this;
    }

    public function preventsOverlapping(): bool
    {
        return $this->overlapExpiresAfterMinutes !== null;
    }

    public function getOverlapExpiresAfterMinutes(): ?int
    {
        return $this->overlapExpiresAfterMinutes;
    }

    /**
     * Stable identity used to key the overlap mutex.
     *
     * @throws SchedulerException When the task has no description
     */
    public function mutexName(): string
    {
        $description = $this->getDescription();

        if ($description === null) {
            throw SchedulerException::overlapRequiresDescription($this->expression);
        }

        return 'schedule-' . sha1($this->expression . $description);
    }

    /**
     * Run the command in a separate process, so the runner moves on to the next due task at once.
     *
     * Only command tasks can run in the background; a closure cannot be passed to another process.
     *
     * @throws SchedulerException When this is a closure task
     */
    public function runInBackground(): self
    {
        if (!$this->action instanceof ScheduledCommand) {
            throw SchedulerException::backgroundRequiresCommand($this->label());
        }

        $this->runsInBackground = true;

        return $this;
    }

    public function runsInBackground(): bool
    {
        return $this->runsInBackground;
    }

    /**
     * The command this task runs, or null for a closure task.
     */
    public function getCommand(): ?ScheduledCommand
    {
        return $this->action instanceof ScheduledCommand ? $this->action : null;
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    /**
     * @throws SchedulerException When this is a command task
     */
    public function getCallback(): Closure
    {
        if (!$this->action instanceof Closure) {
            throw SchedulerException::notAClosureTask('getCallback', $this->label());
        }

        return $this->action;
    }

    public function isDue(
        DateTimeInterface $now,
    ): bool {
        if ($this->timezone !== null) {
            $now = DateTimeImmutable::createFromInterface($now)->setTimezone($this->timezone);
        }

        return CronExpression::matches($this->expression, $now);
    }

    /**
     * Call the closure of a closure task. Command tasks are run by ScheduleRunner.
     *
     * @throws SchedulerException When this is a command task
     */
    public function run(): mixed
    {
        return ($this->getCallback())();
    }

    /**
     * @return array{int, int} hour and minute
     *
     * @throws SchedulerException When the time is malformed or out of range
     */
    private function parseTime(
        string $method,
        string $time,
    ): array {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
            throw SchedulerException::invalidTime($method, $time);
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour > 23 || $minute > 59) {
            throw SchedulerException::invalidTime($method, $time);
        }

        return [$hour, $minute];
    }

    /**
     * @throws SchedulerException
     */
    private function assertHour(
        string $method,
        int $hour,
    ): void {
        if ($hour < 0 || $hour > 23) {
            throw SchedulerException::invalidHour($method, $hour);
        }
    }

    /**
     * @throws SchedulerException
     */
    private function assertMinute(
        string $method,
        int $minute,
    ): void {
        if ($minute < 0 || $minute > 59) {
            throw SchedulerException::invalidMinute($method, $minute);
        }
    }

    /**
     * @throws SchedulerException When the condition returns something other than a bool
     */
    private function evaluateCondition(
        string $method,
        Closure $condition,
    ): bool {
        $result = $condition();

        if (!is_bool($result)) {
            throw SchedulerException::conditionMustReturnBool($method, $this->label(), get_debug_type($result));
        }

        return $result;
    }

    /**
     * How exception messages name this task: its description, or its expression when it has none.
     */
    private function label(): string
    {
        $description = $this->getDescription();

        return $description !== null ? "'$description'" : "with expression '$this->expression'";
    }
}
