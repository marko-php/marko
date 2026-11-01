# marko/scheduler

Fluent task scheduler with cron expression support — define recurring tasks in PHP and run them with a single cron entry.

## Installation

```bash
composer require marko/scheduler
```

## Quick Example

```php
use Marko\Scheduler\Schedule;

return [
    'boot' => function (Schedule $schedule): void {
        $schedule->call(function () {
            // Clean up temp files...
        })->daily()->description('Clean temp files');

        $schedule->call(function () {
            // Import a feed that can outlast its interval...
        })->everyMinute()->description('Import feed')->withoutOverlapping();

        $schedule->command('app:prune', ['--days=30'])->dailyAt('03:30')->runInBackground();
    },
];
```

Run due tasks from a single cron entry with `marko schedule:run` (exits `1` if any task fails), or in the foreground with `marko schedule:work`.

## Documentation

Full usage, API reference, and examples: [marko/scheduler](https://marko.build/docs/packages/scheduler/)
