# Plan: devai docs-driver install prompt

## Created
2026-06-24

## Status
completed

## Objective
When `marko devai:install` runs in an interactive terminal and no docs-search
driver is installed, offer to install the recommended driver (`marko/docs-fts`)
with a Y/n prompt — instead of just printing a "run composer require…" hint —
then build its index. Keep devai depending only on the `marko/docs` contract.

## Related Issues
none

## Discovery Notes
- Follows the docs-vec removal (PR #132): `marko/docs-fts` is the only first-party
  docs driver, but `devai` must stay coupled to the `marko/docs` **contract** only
  (third parties can implement `DocsSearchInterface`). So: no hardcoded driver
  `require`; detect + offer at install time instead.
- **Console framework has no interactive input.** `Marko\Core\Command\Input` is
  `readonly`/parse-only; `Output` is write-only. Decision (user-approved): add a
  small `ConfirmationPrompterInterface` **inside devai** (real `StdinPrompter` +
  test `FakePrompter`), injected into `InstallCommand`. Do NOT expand core's
  console framework for this.
- **`known-drivers.php` is a framework-wide convention**, not a docs-local file:
  shared `KnownDriversValidator` (marko/testing) enforces `array<string,string>`,
  with `KnownDriversValidationTest` + skeleton-suggest parity tests across ~20
  packages. **Do not change its shape.** The resolver reads it as-is; "recommended"
  is already encoded by the existing `(recommended…)` description convention
  (read-only) and is irrelevant while there is exactly one docs driver.
- `CommandRunner` (`proc_open`, `escapeshell*`) already runs `marko …:build`; it
  can run `composer require --dev …` too, and exposes `isOnPath()` to guard it.
- Seam: `InstallCommand` prompts + runs `composer require` **before** delegating to
  `InstallationOrchestrator::install()`. The orchestrator then detects the
  freshly-installed driver and builds its index in a **separate subprocess**
  (`marko docs-fts:build`), so the running process's autoloader is irrelevant.

## Scope

### In Scope
- `ConfirmationPrompterInterface` + `StdinPrompter` (TTY/`--no-interaction` aware) + `FakePrompter`
- `DocsDriverResolver` — registry-driven detect of installed vs. uninstalled known docs drivers
- Generalize `InstallationOrchestrator::buildDocsIndex()` to build whichever known driver is installed (drop the hardcoded `docs-fts` `is_dir`)
- Wire the prompt + opt-in `composer require --dev` into `InstallCommand`, with a non-interactive fallback to today's printed hint

### Out of Scope
- Any change to the `known-drivers.php` shape or the shared `KnownDriversValidator`
- Interactive-input primitives in `marko/core` (kept local to devai)
- Multi-driver selection menu (offer the single recommended driver; opt-out only)
- Persisting a "user declined" flag in `.marko/devai.json` (re-prompt each run)
- Hardcoding `marko/docs-fts` as a devai dependency
- Version/changelog work (rides the next release); docs pages handled by the post-implementation `doc-updater`

## Success Criteria
- [ ] Interactive `devai:install` with no driver offers to install `marko/docs-fts`; on "yes" it runs `composer require --dev marko/docs-fts` and the index builds
- [ ] On "no", or `--no-interaction`, or no TTY: no prompt, no install — falls back to the printed hint (never blocks CI)
- [ ] An already-installed driver is detected and built with no prompt
- [ ] `devai` still requires only `marko/docs` (no concrete-driver dependency added)
- [ ] `known-drivers.php` shape and the shared validator/parity tests are unchanged
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | ConfirmationPrompterInterface + StdinPrompter + FakePrompter | - | completed |
| 002 | DocsDriverResolver (registry-driven driver detection) | - | completed |
| 003 | Orchestrator builds the resolved installed driver | 002 | completed |
| 004 | Wire prompt + opt-in composer require into InstallCommand | 001, 002, 003 | completed |

## Architecture Notes
- Prompter binding goes in `packages/devai/module.php` (`ConfirmationPrompterInterface => StdinPrompter`), alongside the existing `CommandRunnerInterface` binding.
- Build-command convention: a known driver package `marko/<name>` builds via `marko <name>:build` (e.g. `marko/docs-fts` → `docs-fts:build`). The resolver derives this by stripping the `<vendor>/` prefix and appending `:build`. Holds for the only known driver today; per-package overrides are out of scope.
- `StdinPrompter` takes an **injectable input stream** (`$stream = STDIN`) plus a `bool $noInteraction = false` flag so `confirm()`'s parsing is unit-testable against a `php://memory` stream; only the `stream_isatty()` line stays untested. `isInteractive()` = `!$noInteraction && stream_isatty($stream)`. Tests also use `FakePrompter` (scripted answer + interactivity flag) at the command level so no real STDIN is read.
- `--no-interaction` is gated **in `InstallCommand`** via `$input->hasOption('no-interaction')` (verified parseable by `Input::hasOption`), NOT by reconfiguring the injected `readonly` prompter. The prompt path requires `!$noInteraction && $prompter->isInteractive()`.
- The resolver's "installed driver" check reads `vendor/marko/docs/known-drivers.php`; a driver in `vendor/` that is absent from the registry is NOT considered installed. Orchestrator tests must stub that registry file (see task 003) or the existing docs-fts build tests break.
- `InstallCommand` gains `DocsDriverResolver`, `ConfirmationPrompterInterface`, and `CommandRunnerInterface` deps (still `readonly`); guards the install with `isOnPath('composer')` and logs a helpful line on a non-zero exit without throwing. `InstallCommandTest.php` already exists — extend it.
- Post-implementation `doc-updater` updates `ai-assisted-development/mcp.md` + the devai package docs to describe the prompt and keep the "devai depends on the contract, not a driver" note.

## Risks & Mitigations
- **Nested/again composer invocation fails**: only shell out to `composer` from the interactive command path (never from a composer hook context); guard with `isOnPath('composer')` and surface a helpful message on non-zero exit instead of throwing.
- **Reading real STDIN makes tests flaky/hang**: all logic goes through `ConfirmationPrompterInterface`; production reads STDIN only inside `StdinPrompter`, never in tested branches.
- **Accidentally coupling devai to a driver**: the install is a runtime `composer require` chosen by the user, not a composer `require` entry — asserted by a success-criterion test that devai's composer.json gains no driver dependency.
- **Drift from the shared registry convention**: resolver only *reads* `known-drivers.php`; a test asserts the shape/validator are untouched.
