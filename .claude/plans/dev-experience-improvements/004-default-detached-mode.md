# Task 004: Default marko up to detached mode

**Status**: completed
**Depends on**: 003
**Retry count**: 0

## Description
Flip the default behavior of `marko up` from foreground to detached mode. Add a `--foreground`/`-f` flag for users who want foreground mode. Update the config default, command logic, and all related tests.

## Context
- Related files: `packages/dev-server/src/Command/DevUpCommand.php`, `packages/dev-server/config/dev.php`, `packages/dev-server/tests/DevUpCommandTest.php`
- Current logic (line 40): `$detach = hasOption('detach') || hasOption('d') || config('dev.detach')`
- Current config default: `'detach' => false`
- The `Input::hasOption()` method checks if a flag was passed in argv — returns false if absent
- Need to keep `--detach`/`-d` working (now redundant but harmless for scripts)
- Add `--foreground`/`-f` to opt into foreground mode
- 7 existing detach-related tests need updating

## Requirements (Test Descriptions)
- [ ] `it defaults to detached mode when no flags are passed`
- [ ] `it runs in foreground mode when --foreground flag is used`
- [ ] `it runs in foreground mode when -f flag is used`
- [ ] `it reads foreground setting from config when dev.detach is false`
- [ ] `it writes PID file in default detached mode`
- [ ] `it does not call runForeground in default detached mode`
- [ ] `it calls runForeground when --foreground flag is used`

## Acceptance Criteria
- All requirements have passing tests
- `dev.detach` config default changed to `true`
- `--foreground`/`-f` flag opts into foreground mode
- `--detach`/`-d` still works (now redundant but not broken)
- All existing tests updated to match new default
- Code follows project standards

## Implementation Notes
### Config change (`config/dev.php`):
```php
'detach' => true,  // was false
```

### Logic change in `DevUpCommand::execute()`:
```php
// Old:
$detach = $input->hasOption('detach') || $input->hasOption('d') || $this->config->getBool('dev.detach');

// New:
$foreground = $input->hasOption('foreground') || $input->hasOption('f');
$detach = !$foreground && ($input->hasOption('detach') || $input->hasOption('d') || $this->config->getBool('dev.detach'));
```

This means:
- No flags → `$foreground=false`, `$detach=true` (from config) → detached
- `--foreground` → `$foreground=true`, `$detach=false` → foreground
- `--detach` → `$foreground=false`, `$detach=true` (from flag) → detached (redundant but works)
- Config `dev.detach: false` → `$foreground=false`, `$detach=false` → foreground (config override)

### Test updates:
The 7 existing detach-related tests need to be rewritten for the new defaults. Focus on testing:
1. Default behavior (detached, no flags)
2. `--foreground`/`-f` opting into foreground
3. Config override (`dev.detach: false` forces foreground)
4. PID file written in detached mode (now default)
5. `runForeground` called only in foreground mode
