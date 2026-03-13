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
    },
];
```

## Documentation

Full usage, API reference, and examples: [marko/scheduler](https://marko.build/docs/packages/scheduler/)
