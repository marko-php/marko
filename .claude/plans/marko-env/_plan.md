# Plan: marko/env Package

## Created
2026-01-29

## Status
ready

## Objective
Create the `marko/env` package to load `.env` files and provide a global `env()` helper function, enabling environment-specific configuration while keeping config files as the source of truth.

## Scope

### In Scope
- EnvLoader class to parse and load `.env` files
- Global `env()` helper function with type coercion
- Module configuration with proper sequencing (before marko/config)
- Comprehensive README with philosophy and best practices
- Integration with marko/errors-simple Environment class
- Testing with myblog application (curl verification)

### Out of Scope
- Variable interpolation (`${VAR}` syntax)
- Multiple .env files (.env.local, .env.testing)
- Required env var validation (let consuming code fail loudly)
- Reading `.env.example` as fallback

## Success Criteria
- [ ] `env()` function available globally via Composer autoload
- [ ] `.env` files loaded before config files are parsed
- [ ] Apps work without `.env` file (config defaults apply)
- [ ] Type coercion for true/false/null/empty strings
- [ ] marko/errors-simple uses `env('APP_ENV')` for environment detection
- [ ] marko/env included in marko/framework metapackage
- [ ] myblog works with APP_ENV=development showing detailed errors
- [ ] All tests passing
- [ ] README documents philosophy and best practices

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Create env() helper function | - | pending |
| 002 | Create EnvLoader class | - | pending |
| 003 | Create package structure and module.php | 001, 002 | pending |
| 004 | Create comprehensive README | 001, 002 | pending |
| 005 | Update errors-simple Environment class | 001 | pending |
| 006 | Add marko/env to marko/framework | 003 | pending |
| 007 | Test with myblog application | 003, 005, 006 | pending |

## Architecture Notes

### Key Principle: Config Files Are Source of Truth
Unlike Laravel where `.env` is the source of truth, in Marko:
- Config files define ALL options with sensible defaults
- `.env` provides overrides for secrets and environment-specific values
- Apps work out of the box without any `.env` file

### Load Sequence
```
1. Composer autoloader (loads env() function via files autoload)
2. Bootstrap starts
3. Modules discovered and sorted
4. marko/env boot callback loads .env file
5. marko/config boot callback loads config files (env() now works)
6. Rest of application boots
```

### Package Structure
```
packages/env/
  src/
    EnvLoader.php      # Parses .env files
    functions.php      # Global env() helper
  tests/
    Unit/
      EnvLoaderTest.php
      FunctionsTest.php
  composer.json        # With files autoload for functions.php
  module.php           # Boot callback + sequence before marko/config
  README.md            # Philosophy and best practices
```

## Risks & Mitigations
- **Risk**: env() called before .env loaded → **Mitigation**: Returns default value (expected behavior)
- **Risk**: Circular dependency with config → **Mitigation**: Sequence hints ensure env loads first
- **Risk**: Performance with large .env files → **Mitigation**: Simple line-by-line parsing, no complex features
