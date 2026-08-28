# Task 012: Expose resolvedInstances on ContainerInterface

**Status**: completed
**Depends on**: 011
**Retry count**: 0

## Description
Add `resolvedInstances()` to `ContainerInterface` so it is reachable through the type `Application::$container` is declared as. Without this, a long-running process cannot discover what to reset without reaching for the concrete `Container`.

## Context
- Modify: `packages/core/src/Container/ContainerInterface.php` and confirm `packages/core/src/Container/Container.php` still satisfies it
- `Application::$container` is `public private(set) ContainerInterface` (`Application.php:64`), so `$app->container->resolvedInstances(...)` currently does not type-check — the accessor added in task 011 lives only on the concrete class.
- `Container` is the **only** implementor of `Marko\Core\Container\ContainerInterface` (verified). The interface extends `Psr\Container\ContainerInterface`. Adding a method is a BC break for any third-party implementor, which is why it belongs here, pre-1.0, rather than in the follow-up worker PR.
- Keep the signature identical to the concrete method so nothing else changes: `resolvedInstances(?string $interface = null): array`, returning already-resolved instances keyed by binding identifier, optionally filtered to those implementing a given interface.
- **It must never force instantiation** — that contract is the whole point and must be restated in the interface docblock.
- `packages/core/src` is covered by `phpstan.neon` at level 6 and must stay at zero errors.

## Requirements (Test Descriptions)
- [x] `it declares resolved instances on the container contract`
- [x] `it resolves already built instances through the interface type`
- [x] `it filters resolved instances by interface through the interface type`
- [x] `it documents that the accessor never forces instantiation`

## Acceptance Criteria
- All requirements have passing tests
- `Container` satisfies the extended interface with no signature change
- All pre-existing core tests pass unmodified
- PHPStan level 6 clean
- Code follows code standards

## Implementation Notes
- Added `resolvedInstances(?string $interface = null): array` to `ContainerInterface` with a docblock restating the never-forces-instantiation contract (byte-identical signature to `Container::resolvedInstances()`, so `Container` satisfies it unchanged).
- New test file `packages/core/tests/Unit/Container/ContainerInterfaceTest.php` tests through the `ContainerInterface` type (not the concrete class) per the task's "test the contract" requirement — one test per requirement, using `assert($typed instanceof ContainerInterface)` to keep the static type as the interface when calling the method.
- `ContainerInterface` is implemented by test-double stubs across several other packages (anonymous/concrete classes in test files), which is a real BC break beyond `Container`. Added the minimal `resolvedInstances(): array { return []; }` (or equivalent, filtering/returning tracked instances where the stub already tracked them) method to each so the whole repo's test suite keeps compiling: `packages/core/tests/Unit/Plugin/PluginInterceptionTest.php`, `packages/webhook/tests/Jobs/{SerializableWebhookJobTest,DispatchWebhookJobRetryTest,DispatchWebhookJobTest}.php`, `packages/notification/tests/Unit/SerializableNotificationJobTest.php`, `packages/layout/tests/Unit/Helpers.php`, `packages/page-cache/tests/Unit/Middleware/PageCacheMiddlewareTest.php`, `packages/queue/tests/{WorkerTest,AsyncObserverJobTest,Feature/IntegrationTest}.php`, `packages/errors-advanced/tests/Unit/AdvancedErrorHandlerTest.php`. These are mechanical stub updates, not behavioral test changes.
- Deliberately left `packages/database-readwrite/tests/**` untouched — per task context, that package is being edited concurrently by a sibling worker and is out of scope; its `ContainerInterface` stubs will fail to compile until that worker (or a follow-up) adds the method. `packages/inertia` has no `ContainerInterface` implementors, so nothing to do there.
- Verified via a temporary scoped PHPUnit config (excluding `database-readwrite` and `inertia`) that the rest of the monorepo's test suite passes; the only other observed failures (`tests/PackagingTest.php`, `tests/IntegrationVerificationTest.php`) are pre-existing and caused by an unrelated, incomplete `packages/roadrunner` scaffold being built by a different concurrent process — unrelated to this change.
- Core suite: 571 tests passed. PHPStan (`packages/core/src`, level 6): no errors. PHPCS and php-cs-fixer: clean on all touched files.
