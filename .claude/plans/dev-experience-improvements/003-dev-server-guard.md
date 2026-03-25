# Task 003: Add public/index.php guard to DevUpCommand

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Add a check in `DevUpCommand` at the top of `execute()` (after reading config) to verify `public/index.php` exists. If missing, show a helpful error with the exact code needed to create it. The guard runs before any processes start to avoid orphaning Docker/frontend processes.

## Context
- Related files: `packages/dev-server/src/Command/DevUpCommand.php`, `packages/dev-server/tests/DevUpCommandTest.php`
- The guard goes at the top of `execute()`, after reading config values but before any process starts
- The error should show the minimal `public/index.php` content using the new `Application::boot()` API
- Currently, without the guard, the PHP built-in server just says "Directory public/ does not exist" and exits with code 1 — unhelpful
- The DevUpCommand needs to know the project root to check for `public/index.php`. It can derive this from the config or inject `ProjectPaths`.

## Requirements (Test Descriptions)
- [ ] `it throws DevServerException when public/index.php does not exist`
- [ ] `it includes helpful message with bootstrap code in the exception`
- [ ] `it throws before any processes start when public/index.php is missing`
- [ ] `it starts PHP server normally when public/index.php exists`

## Acceptance Criteria
- All requirements have passing tests
- Error message includes the exact `Application::boot()` code to create
- Guard runs before any processes start (no orphaned processes on failure)
- Code follows project standards

## Implementation Notes
**Important -- orphaned process risk**: If the guard is placed after Docker/frontend/custom processes start, throwing an exception will leave those processes running but untracked (PID file never written, `runForeground()` never called). To avoid this, place the guard **before all process starts** at the top of `execute()`, right after reading config values. While the plan originally said "other processes can start without it," in practice a user without `public/index.php` needs to fix that first before any dev services are useful.

The guard should check for `public/index.php` relative to the project root. The project root can be derived from `ProjectPaths` (already registered in the container during boot).

Add `ProjectPaths` to the constructor:
```php
public function __construct(
    private ConfigRepositoryInterface $config,
    private DockerDetector $dockerDetector,
    private FrontendDetector $frontendDetector,
    private PubSubDetector $pubsubDetector,
    private PidFile $pidFile,
    private ProcessManager $processManager,
    private ProjectPaths $paths,
) {}
```

**Test helper update required**: The `createDevUpCommand()` helper in `DevUpCommandTest.php` must be updated to pass a `ProjectPaths` instance. Use the existing `$dir` (temp directory) as the base path: `new ProjectPaths($dir)`. Also create `$dir/public/index.php` in the helper so existing tests don't break. Tests for the guard itself should use a temp dir WITHOUT `public/index.php`.

Place the guard at the top of `execute()`, after reading config values:
```php
$indexPath = $this->paths->base . '/public/index.php';
if (!file_exists($indexPath)) {
    throw new DevServerException(
        message: 'Cannot start PHP server: public/index.php not found.',
        context: "While starting PHP development server (expected at $indexPath)",
        suggestion: "Create public/index.php with:\n\n" .
            "<?php\n\n" .
            "declare(strict_types=1);\n\n" .
            "require __DIR__ . '/../vendor/autoload.php';\n\n" .
            "use Marko\\Core\\Application;\n\n" .
            "\$app = Application::boot(dirname(__DIR__));\n" .
            "\$app->handleRequest();\n",
    );
}
```
