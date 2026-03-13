---
title: Create a Custom Module
description: Build a reusable Marko module from scratch --- the right way.
---

This tutorial walks you through creating a module that other Marko applications can install via Composer. We'll build a simple analytics module that tracks page views.

## What You'll Build

- A reusable Composer-installable Marko module
- An interface/implementation pair for page view analytics
- Middleware that automatically tracks page views
- Unit tests for the analytics logic

## Prerequisites

- PHP 8.5+
- Composer 2.x
- A working Marko project (see [Build a Blog](/docs/tutorials/build-a-blog/))

## Step 1: Module Structure

Create the module directory:

```
packages/analytics/
├── src/
│   ├── AnalyticsInterface.php
│   ├── DatabaseAnalytics.php
│   ├── Middleware/
│   │   └── TrackPageViewMiddleware.php
│   └── Observer/
│       └── LogPageView.php
├── config/
│   └── analytics.php
├── database/
│   └── migrations/
├── tests/
│   └── Unit/
├── composer.json
└── module.php
```

## Step 2: Define the Interface

Always start with the contract:

```php title="src/AnalyticsInterface.php"
<?php

declare(strict_types=1);

namespace Marko\Analytics;

interface AnalyticsInterface
{
    public function trackPageView(string $path, ?string $userId = null): void;

    public function getPageViews(string $path): int;
}
```

## Step 3: Implement It

```php title="src/DatabaseAnalytics.php"
<?php

declare(strict_types=1);

namespace Marko\Analytics;

use Marko\Database\Query\QueryBuilderInterface;
use DateTimeImmutable;

class DatabaseAnalytics implements AnalyticsInterface
{
    public function __construct(
        private readonly QueryBuilderInterface $queryBuilder,
    ) {}

    public function trackPageView(string $path, ?string $userId = null): void
    {
        $this->queryBuilder->table('page_views')->insert([
            'path' => $path,
            'user_id' => $userId,
            'viewed_at' => new DateTimeImmutable(),
        ]);
    }

    public function getPageViews(string $path): int
    {
        return $this->queryBuilder->table('page_views')
            ->where('path', '=', $path)
            ->count();
    }
}
```

## Step 4: Wire It Up

```php title="module.php"
<?php

declare(strict_types=1);

use Marko\Analytics\AnalyticsInterface;
use Marko\Analytics\DatabaseAnalytics;

return [
    'bindings' => [
        AnalyticsInterface::class => DatabaseAnalytics::class,
    ],
    'singletons' => [
        DatabaseAnalytics::class,
    ],
];
```

## Step 5: Create the `composer.json`

```json title="composer.json"
{
    "name": "marko/analytics",
    "description": "Page view analytics for Marko applications",
    "type": "marko-module",
    "require": {
        "php": ">=8.5",
        "marko/core": "^1.0",
        "marko/database": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "Marko\\Analytics\\": "src/"
        }
    },
    "extra": {
        "marko": {
            "module": true
        }
    }
}
```

## Step 6: Add Middleware

```php title="src/Middleware/TrackPageViewMiddleware.php"
<?php

declare(strict_types=1);

namespace Marko\Analytics\Middleware;

use Marko\Analytics\AnalyticsInterface;
use Marko\Authentication\AuthManager;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

class TrackPageViewMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AnalyticsInterface $analytics,
        private readonly AuthManager $authManager,
    ) {}

    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $response = $next($request);

        // Track after the response to avoid slowing down the request
        $user = $this->authManager->user();

        $this->analytics->trackPageView(
            path: $request->path(),
            userId: $user?->getIdentifier(),
        );

        return $response;
    }
}
```

## Step 7: Write Tests

```php title="tests/Unit/DatabaseAnalyticsTest.php"
<?php

declare(strict_types=1);

use Marko\Analytics\DatabaseAnalytics;

test('tracks a page view', function () {
    $connection = createTestConnection();
    $analytics = new DatabaseAnalytics(queryBuilder: $connection);

    $analytics->trackPageView('/blog/hello-world');

    expect($analytics->getPageViews('/blog/hello-world'))->toBe(1);
});

test('counts page views for a specific path', function () {
    $connection = createTestConnection();
    $analytics = new DatabaseAnalytics(queryBuilder: $connection);

    $analytics->trackPageView('/blog/hello-world');
    $analytics->trackPageView('/blog/hello-world');
    $analytics->trackPageView('/about');

    expect($analytics->getPageViews('/blog/hello-world'))->toBe(2)
        ->and($analytics->getPageViews('/about'))->toBe(1);
});
```

## What You've Learned

- How to structure a Marko module with proper directory layout
- Separating interface from implementation for extensibility
- Wiring bindings and singletons in `module.php`
- Creating a `composer.json` with the `marko-module` type
- Building middleware that integrates with the [request lifecycle](/docs/packages/routing/)
- Writing unit tests for module functionality

## Next Steps

- [Modularity](/docs/concepts/modularity/) --- understand the module system in depth
- [Preferences](/docs/concepts/preferences/) --- let users swap your implementation
- [Plugins](/docs/concepts/plugins/) --- let users extend your methods
