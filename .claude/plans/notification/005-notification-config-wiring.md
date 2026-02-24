# Task 005: Notification Interface Package Config and Wiring

**Status**: done
**Depends on**: 001, 002, 003, 004
**Retry count**: 0

## Description
Create the package scaffolding and wiring for `marko/notification`: composer.json, module.php, config/notification.php, NotificationConfig class, and Pest.php. The module.php boot callback registers channels based on available dependencies (mail, database).

## Context
- Namespace: `Marko\Notification\`
- Package: `marko/notification`
- Dependencies: marko/core, marko/config
- Suggests: marko/mail, marko/database, marko/queue
- Reference: packages/mail/composer.json, packages/queue/module.php

### Config File
```php
// config/notification.php
return [
    'channels' => ['mail', 'database'],
];
```

### NotificationConfig
```php
// src/Config/NotificationConfig.php
class NotificationConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    /** @return array<string> */
    public function channels(): array;
}
```

### module.php
```php
return [
    'enabled' => true,
    'bindings' => [
        NotificationConfig::class => NotificationConfig::class,
        NotificationManager::class => NotificationManager::class,
        NotificationSender::class => NotificationSender::class,
    ],
    'boot' => function ($container) {
        $manager = $container->get(NotificationManager::class);

        // Register mail channel if mailer is available
        if ($container->has(MailerInterface::class)) {
            $manager->register('mail', $container->get(MailChannel::class));
        }

        // Register database channel if connection is available
        if ($container->has(ConnectionInterface::class)) {
            $manager->register('database', $container->get(DatabaseChannel::class));
        }
    },
];
```

### composer.json
```json
{
    "name": "marko/notification",
    "description": "Notification system interfaces and channels for the Marko Framework",
    "type": "marko-module",
    "require": {
        "php": "^8.5",
        "marko/core": "^1.0",
        "marko/config": "^1.0"
    },
    "suggest": {
        "marko/mail": "Required for mail channel notifications",
        "marko/database": "Required for database channel notifications",
        "marko/queue": "Required for queued notification sending"
    },
    "autoload": {
        "psr-4": {
            "Marko\\Notification\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Marko\\Notification\\Tests\\": "tests/"
        }
    },
    "extra": {
        "marko": {
            "module": true
        }
    }
}
```

## Requirements (Test Descriptions)
- [ ] `it has marko module flag in composer.json`
- [ ] `it has correct PSR-4 autoloading namespace`
- [ ] `it requires marko/core and marko/config`
- [ ] `it returns valid module configuration array with bindings`
- [ ] `it loads default channels from config`
- [ ] `it registers mail channel in boot when MailerInterface is available`
- [ ] `it registers database channel in boot when ConnectionInterface is available`

## Acceptance Criteria
- composer.json with correct name, type, require, suggest, autoload, extra
- No hardcoded version in composer.json
- module.php with bindings for NotificationConfig, NotificationManager, NotificationSender
- module.php boot callback conditionally registers channels
- config/notification.php with default channels array
- NotificationConfig reads channels from ConfigRepositoryInterface
- Pest.php configured for the package tests

## Implementation Notes
(Left blank)
