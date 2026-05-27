# Task 011: Roll out known-drivers pattern — marko/inertia

**Status**: pending
**Depends on**: 001, 005
**Retry count**: 0

## Description
Apply the pilot pattern to `marko/inertia`. Three drivers: `inertia-react`, `inertia-svelte`, `inertia-vue`. All bind `InertiaFrontendInterface` and are mutually exclusive.

## Context
- Interface: `Marko\Inertia\Frontend\InertiaFrontendInterface` (verified at `packages/inertia/src/Frontend/InertiaFrontendInterface.php`)
- Drivers: `marko/inertia-react`, `marko/inertia-svelte`, `marko/inertia-vue`
- Recommended-first ordering: `inertia-react` (largest community, most documentation/tooling); svelte and vue follow in alphabetical order
- Confirmed in audit: all three bind `InertiaFrontendInterface` via module.php
- **`marko/inertia` does NOT currently have a `NoDriverException`** — it must be CREATED in this task (not just refactored). Follow the same shape as `packages/database/src/Exceptions/NoDriverException.php` (post-task-003 refactor): extends a package-local exception base class or `MarkoException` directly, has a `noDriverInstalled()` static factory that reads from `known-drivers.php` and renders the package list with docs URLs.

**Description text for known-drivers.php:**
- `marko/inertia-react` → `'React frontend driver for Inertia.js (recommended — largest community and tooling ecosystem)'`
- `marko/inertia-svelte` → `'Svelte frontend driver for Inertia.js'`
- `marko/inertia-vue` → `'Vue frontend driver for Inertia.js'`

## Sub-steps
1. Create `packages/inertia/known-drivers.php`
2. **Create** `packages/inertia/src/Exceptions/NoDriverException.php` (file does not exist yet) using the established pattern from task 003. Add a corresponding `packages/inertia/tests/Exceptions/NoDriverExceptionTest.php`.
3. Add mutual `conflict` blocks to all three driver composer.json files (each lists the other two)
4. Add `packages/inertia/tests/KnownDriversValidationTest.php` using `KnownDriversValidator`
5. Add `marko/testing` to `packages/inertia/composer.json` `require-dev` (needed for the validation test)

**Note:** Inertia drivers represent frontend framework choice — fundamentally not mixable (a single Inertia app uses one frontend). The conflict declaration is correct here.

## Requirements (Test Descriptions)
- [ ] `it ships a known-drivers.php file listing all three inertia drivers`
- [ ] `it lists marko/inertia-react first as the recommended driver`
- [ ] `inertia NoDriverException reads from known-drivers.php and includes docs URLs`
- [ ] `each inertia driver declares conflict with both other drivers`
- [ ] `validation test confirms conflict blocks match known-drivers list`

## Acceptance Criteria
- `packages/inertia/known-drivers.php` exists with three entries
- `NoDriverException` exists (created if missing) and reads from known-drivers.php
- All three driver composer.json files have correctly-populated `conflict` blocks listing both siblings
- Validation test passes
- Existing inertia tests still pass
- Code follows code standards
