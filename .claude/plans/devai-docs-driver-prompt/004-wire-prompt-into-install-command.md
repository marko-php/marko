# Task 004: Wire prompt + opt-in composer require into InstallCommand

**Status**: pending
**Depends on**: 001, 002, 003
**Retry count**: 0

## Description
Make `marko devai:install` offer to install the recommended docs driver when none
is present and the session is interactive. On "yes" it runs
`composer require --dev <package>` (so the orchestrator then builds its index); on
"no", `--no-interaction`, or a non-TTY session it does nothing extra and falls back
to the existing printed hint. Never blocks CI.

## Context
- Edit: `packages/devai/src/Commands/InstallCommand.php` (still `readonly`).
  Add constructor deps: `DocsDriverResolver`, `ConfirmationPrompterInterface`,
  `CommandRunnerInterface`. These all autowire/bind (resolver has no args;
  `ConfirmationPrompterInterface` is bound in task 001; `CommandRunnerInterface`
  is already bound) so `ContainerWiringTest` keeps resolving `InstallCommand`.
- **`--no-interaction` handling — do NOT try to reconfigure the injected prompter.**
  The prompter is injected `readonly` and the container builds `StdinPrompter` with
  its default flag; the command cannot rebuild it per-invocation. Instead, the command
  reads `$noInteraction = $input->hasOption('no-interaction')` itself and gates the
  prompt on BOTH that flag and `$prompter->isInteractive()`. Concretely, treat the
  session as promptable only when `!$noInteraction && $prompter->isInteractive()`.
  This means `--no-interaction` is a real, parseable long option (verified against
  `Marko\Core\Command\Input::hasOption()` — multi-char names match `--no-interaction`),
  so no unknown-option handling is needed; an absent flag simply returns false.
- Note `ConfirmationPrompterInterface::confirm(string $question, bool $default): bool`
  (matches task 001's signature — pass `default: true`).
- Flow, BEFORE calling `$this->orchestrator->install(...)`:
  1. If `$resolver->installedDriver($projectRoot)` !== null → do nothing (orchestrator builds it).
  2. Else let `$pkg = $resolver->recommendedUninstalled($projectRoot)`. If `$pkg`
     is null → do nothing (orchestrator logs the hint).
  3. Else if `$noInteraction || !$prompter->isInteractive()` → do nothing (orchestrator logs the hint).
  4. Else `confirm("No docs search driver installed. Install $pkg to enable search_docs?", default: true)`:
     - yes + `CommandRunner::isOnPath('composer')` → `run('composer', ['require','--dev',$pkg])`;
       on non-zero exit write a helpful line (do NOT throw — never block the install). On
       success, fall through so the orchestrator detects and builds it.
     - yes + composer not on PATH → write a helpful "composer not found — run `composer require --dev $pkg`" line.
     - no → fall through to the orchestrator's hint.
- Then call the orchestrator as today and print the summary.
- Tests: `packages/devai/tests/Unit/Commands/InstallCommandTest.php` **already exists**
  with two passing tests (`supports non-interactive mode via the --agents flag`,
  `is registered via Command attribute…`) — **ADD** the new cases to it, do not
  overwrite or remove the existing ones. Construct `InstallCommand` directly with a
  `FakePrompter` (task 001), a recording `CommandRunnerInterface` (reuse the
  `makeRecordingRunner()` pattern), a real `DocsDriverResolver`, and the existing
  orchestrator/registry doubles. Drive it through a temp project root
  (`devaiTempDir()`) with/without `vendor/marko/*` dirs and a stub
  `vendor/marko/docs/known-drivers.php`. Note the command reads `getcwd()` for the
  project root — either `chdir()` into the temp root in the test (restore cwd in
  `afterEach`) or assert at the level the resolver/runner are invoked; prefer the
  `chdir()` approach to exercise the real path. Assert on the recorded command calls
  (e.g. `['composer', ['require','--dev','marko/docs-fts']]`).
- Add a guard test that devai's own `composer.json` gains no driver dependency
  (still requires only `marko/docs`): decode `packages/devai/composer.json`, assert
  `require` contains `marko/docs` and contains NO key matching `marko/docs-*`
  (i.e. no concrete docs driver). This protects the modularity invariant.

## Requirements (Test Descriptions)
- [ ] `it offers to install the recommended driver and runs composer require on yes`
- [ ] `it does not install anything when the user answers no`
- [ ] `it does not prompt or install when run with --no-interaction`
- [ ] `it does not prompt or install when the session is not interactive (no TTY)`
- [ ] `it does not prompt when a docs driver is already installed`
- [ ] `it writes a helpful message when composer is not on PATH`
- [ ] `it writes a helpful message and does not throw when composer require exits non-zero`
- [ ] `it keeps devai dependent on the marko/docs contract only (no driver in require)`

## Acceptance Criteria
- All requirements have passing tests
- The two pre-existing tests in `InstallCommandTest.php` remain green
- `ContainerWiringTest` still resolves `InstallCommand` (new deps autowire/bind)
- Non-interactive / no-driver path is unchanged from today (printed hint, exit 0)
- A non-zero `composer require` exit is surfaced as a log/output line, never thrown — `execute()` still returns 0
- `devai/composer.json` require still contains `marko/docs` and no concrete driver
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
