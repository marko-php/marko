# Devil's Advocate Review: no-driver-exception-convention

## Critical (Must fix before building)

### C1: Mail package uses `Exception/` (singular) namespace -- container convention will miss it (Task 006, Task 001)

The container convention in task 001 looks for `Marko\{Package}\Exceptions\NoDriverException` (plural). However, the mail package's primary exception classes live under `Marko\Mail\Exception` (singular namespace, `src/Exception/` directory). There is also a `src/Exceptions/` directory but it only contains a secondary `MessageException`.

If the `NoDriverException` is placed in `Marko\Mail\Exceptions\NoDriverException` (to match the convention), it will work with the container lookup. But task 006's context references `packages/mail/src/Exception/MailException.php` and says "check whether the mail package uses `Exception/` or `Exceptions/`". The worker needs explicit instruction: the `NoDriverException` **must** be placed in `src/Exceptions/NoDriverException.php` (namespace `Marko\Mail\Exceptions`) to match the container convention, regardless of where `MailException` lives.

**Fix:** Update task 006 to explicitly state the file goes in `packages/mail/src/Exceptions/NoDriverException.php` with namespace `Marko\Mail\Exceptions`, and that it extends `MarkoException` directly (not `MailException`, which is in a different namespace).

### C2: `NotificationException::noQueueAvailable()` is called from runtime code -- task 019 must not remove it (Task 019)

Task 019 says to remove hardcoded driver suggestions from `NotificationException`. However, `NotificationException::noQueueAvailable()` is called from `packages/notification/src/NotificationSender.php` line 62. This is a runtime call, not just a test. The task's implementation notes say "verify they are not called from runtime code" but the requirement line says "NotificationException no longer contains hardcoded driver package suggestion" without qualifying which methods.

The `noQueueAvailable()` method is about a missing **queue** driver, not a missing **notification** driver, so it serves a fundamentally different purpose than `NoDriverException`. It should be kept but its suggestion text could reference the queue package's `NoDriverException` conceptually, or simply be left alone since it's about a cross-package dependency.

**Fix:** Update task 019 to explicitly exclude `NotificationException::noQueueAvailable()` from removal, noting it is called from runtime code in `NotificationSender.php`.

### C3: `BindingException` tests assert suggestion contains "bind" -- will break if method signature changes (Task 001)

The existing test at `packages/core/tests/Unit/Exceptions/ExceptionsTest.php` line 142 asserts:
```php
$bindingException = BindingException::noImplementation('SomeInterface');
expect($bindingException->getSuggestion())->not->toBeEmpty()->toContain('bind');
```

Task 001 must ensure the simplified `noImplementation()` method (after removing filesystem scanning) still includes the word "bind" in its suggestion. The task requirements mention "BindingException noImplementation still works as generic fallback" but don't specify maintaining backward-compatible assertion targets.

**Fix:** Add a note to task 001 that the simplified `noImplementation()` must keep the word "binding" or "bind" in its suggestion to avoid breaking existing tests.

## Important (Should fix before building)

### I1: `DatabaseException::noDriverInstalled(string $driver)` serves a different purpose than `NoDriverException::noDriverInstalled()` (Task 003, Task 019)

The existing `DatabaseException::noDriverInstalled(string $driver)` takes a specific driver name (e.g., "mysql") and tells you which specific package to install. The new `NoDriverException::noDriverInstalled()` is a zero-argument method that lists ALL available driver packages. These serve different purposes:
- `DatabaseException::noDriverInstalled('mysql')` = "You configured MySQL but the MySQL driver package isn't installed"
- `NoDriverException::noDriverInstalled()` = "No database driver is bound at all"

Task 019's requirement says "DatabaseException noDriverInstalled method is removed if unused in runtime code." While it's true it's not called from runtime `src/` code currently, it represents a valid use case that driver packages may call. The task should be more careful here.

**Fix:** Update task 019 to state that `DatabaseException::noDriverInstalled(string $driver)` should be **kept** since it serves a different purpose (specific driver not installed vs. no driver at all), and its `DRIVER_PACKAGES` constant should be renamed to avoid confusion with the new `NoDriverException::DRIVER_PACKAGES`.

### I2: `DriverRegistry` hardcoded suggestion is runtime code (Task 019, Task 008)

The `packages/filesystem/src/Discovery/DriverRegistry.php` line 42 has a hardcoded suggestion `'For local storage: composer require marko/filesystem-local'`. This is thrown from runtime code (the `get()` method). Task 019 lists this file for cleanup but the method serves a different purpose -- it's about an unknown driver name at runtime, not about no driver being installed at all.

**Fix:** Update task 019 to clarify that `DriverRegistry::get()` should keep its suggestion (it's about an unknown driver name, not about no drivers installed) or update it to reference `NoDriverException::DRIVER_PACKAGES` for the package list.

### I3: Task 001 container test needs a real `NoDriverException` fixture in a Marko namespace (Task 001)

Task 001 says "create a minimal test fixture `NoDriverException` in the test namespace." However, the container convention extracts the namespace `Marko\{Package}` and looks for `Marko\{Package}\Exceptions\NoDriverException`. A test fixture needs to be in a namespace like `Marko\TestPackage\Exceptions\NoDriverException` to be discoverable by the convention. The test file `ContainerTest.php` currently defines fixture classes in the global namespace (no namespace declaration). The worker needs to either create a separate fixture file with the right namespace or use inline anonymous classes carefully.

**Fix:** Add explicit guidance to task 001 that the test fixture must be a real class in a `Marko\{Something}\Exceptions` namespace (e.g., a fixture file at the test level), not just an inline class in the global namespace.

### I4: Task 001 doesn't specify what happens to `BindingException::noImplementation` suggestion text (Task 001)

Currently `noImplementation()` builds a suggestion with "Option 1: Install an available driver package" and "Option 2: Register a binding in module.php" when drivers are found. After removing the filesystem scanning, should it just say "Register a binding in module.php"? The task says "remove `discoverDriverPackages`, `scanForDriverPackages`, update `noImplementation`" but doesn't specify the new suggestion text. Workers building tasks 002-018 need to know the exact `noImplementation()` behavior to write their tests.

**Fix:** Add explicit expected behavior to task 001: `noImplementation()` should become a simple method that just suggests registering a binding in module.php (no driver suggestions), since the container will throw `NoDriverException` instead for Marko interfaces that have drivers.

### I5: `MailException::noDriverInstalled()` is not called from runtime code but `configFileNotFound()` is on the same class (Task 019)

Task 019 says to clean up `MailException` hardcoded suggestions. `MailException::noDriverInstalled()` is only called from tests, so it can be removed. But the class also has `configFileNotFound()` which IS called from runtime code (`MailConfig.php`). The task must not remove the entire class.

**Fix:** Clarify in task 019 that only `MailException::noDriverInstalled()` should be removed from `MailException`, not the entire class or other methods.

### I6: `QueueException::noDriverInstalled()` may need to stay for `configFileNotFound()` coexistence (Task 019)

Similar to mail, `QueueException` has both `noDriverInstalled()` and `configFileNotFound()`. The `noDriverInstalled()` is not called from runtime code and can be removed, but `configFileNotFound()` must stay. Task 019 should be explicit about removing only the `noDriverInstalled()` method.

**Fix:** Clarify in task 019 that only `QueueException::noDriverInstalled()` should be removed, not the `configFileNotFound()` method.

## Minor (Nice to address)

### M1: The `dev-develop as 0.1.0` version constraint in `BindingException` suggestion will be stale

The current `noImplementation()` suggestion includes `composer require $pkg:"dev-develop as 0.1.0"` which is a development-era version constraint. After simplifying the method, this goes away naturally, but any new `NoDriverException` classes should use plain `composer require marko/package-name` without version constraints. The plan's template already does this correctly.

### M2: Task sizing -- tasks 002-018 are very uniform and small

Each of tasks 002-018 creates a single exception class with one method and one test file. These could be batched (e.g., 3-4 packages per task) without losing clarity. As-is, 17 nearly identical tasks create orchestration overhead. Not a blocker but worth noting.

### M3: The `errors` package has no `Contracts/` directory mention -- verify interface exists

Task 015 references `packages/errors/src/Contracts/ErrorHandlerInterface.php` for context. This exists, but the NoDriverException's context message should reference the actual interfaces in the package. The worker should check what interfaces exist.

## Questions for the Team

### Q1: Should `NoDriverException` extend the package's base exception or `MarkoException`?

The plan says packages whose base exception extends `Exception` (not `MarkoException`) should have `NoDriverException` extend `MarkoException` directly. This means `NoDriverException` won't be catchable by `catch (CacheException $e)` or `catch (SessionException $e)`. Is this intentional? It creates an inconsistency where some `NoDriverException` classes are part of the package exception hierarchy and others aren't.

### Q2: Should the container check trigger for ALL unbound Marko interfaces or only those in interface-only packages?

The convention checks any `Marko\{Package}\...` namespace. What about interfaces defined in packages that are NOT interface-only packages? For example, `Marko\Core\Container\ContainerInterface` -- if someone tried to resolve that without a binding, the container would look for `Marko\Core\Exceptions\NoDriverException` which won't exist. This is fine (it falls back to `BindingException`), but worth confirming this edge case is expected.

### Q3: What about the `FilesystemManager` hardcoded suggestion?

Task 019 lists `packages/filesystem/src/Manager/FilesystemManager.php` as having `'Ensure the driver package is installed'`. However, grep found no `composer require` in that directory. Was this based on stale data? The actual hardcoded suggestion is only in `DriverRegistry.php`.
