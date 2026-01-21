# Plan: Framework Metapackage (marko/framework)

## Created
2026-01-21

## Status
pending

## Objective
Create the `marko/framework` metapackage that bundles all commonly-used Marko framework packages for easy installation. This provides a single-command setup for new projects while allowing individual package installation for advanced users.

## Scope

### In Scope
- `marko/framework` composer metapackage (no code, just dependencies)
- Bundles core packages for typical web applications
- Version constraints that ensure compatibility between packages
- Clear documentation of what's included vs. optional packages

### Out of Scope
- Any actual code (this is purely a dependency aggregator)
- Application scaffolding or project templates (separate: `marko/installer`)
- IDE plugins or tooling
- Documentation website

## Success Criteria
- [ ] `composer require marko/framework` installs complete web stack
- [ ] All bundled packages are compatible with each other
- [ ] Version constraints prevent incompatible combinations
- [ ] README clearly documents included vs. optional packages
- [ ] Package follows Composer best practices for metapackages

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Create composer.json with all dependencies | - | pending |
| 002 | Write README documenting included packages | 001 | pending |
| 003 | Test installation in clean project | 001 | pending |

## Architecture Notes

### Included Packages (Core Web Stack)
```json
{
    "name": "marko/framework",
    "description": "The Marko PHP Framework",
    "type": "metapackage",
    "license": "MIT",
    "require": {
        "php": "^8.5",
        "marko/core": "^1.0",
        "marko/routing": "^1.0",
        "marko/cli": "^1.0",
        "marko/errors": "^1.0",
        "marko/errors-simple": "^1.0",
        "marko/config": "^1.0",
        "marko/hashing": "^1.0",
        "marko/validation": "^1.0"
    },
    "suggest": {
        "marko/database": "For database abstraction and entity management",
        "marko/database-mysql": "MySQL/MariaDB database driver",
        "marko/database-pgsql": "PostgreSQL database driver",
        "marko/cache": "Caching abstraction layer",
        "marko/cache-file": "File-based cache driver",
        "marko/session": "Session management",
        "marko/session-file": "File-based session driver",
        "marko/view": "Template rendering abstraction",
        "marko/view-latte": "Latte template engine driver",
        "marko/auth": "Authentication system with guards and middleware",
        "marko/mail": "Email sending abstraction",
        "marko/mail-smtp": "SMTP mail driver",
        "marko/log": "PSR-3 compatible logging",
        "marko/log-file": "File-based logging driver",
        "marko/queue": "Background job processing",
        "marko/queue-database": "Database-backed queue driver",
        "marko/filesystem": "Filesystem abstraction",
        "marko/filesystem-local": "Local filesystem driver",
        "marko/errors-advanced": "Pretty error pages for development"
    }
}
```

### Package Categories

**Always Included (Core):**
- `marko/core` - Bootstrap, DI container, module loader, extensibility (Preferences, Plugins, Events)
- `marko/routing` - HTTP routing with PHP attributes
- `marko/cli` - Console commands infrastructure
- `marko/errors` - Error handling interfaces
- `marko/errors-simple` - Basic error formatters
- `marko/config` - Configuration management
- `marko/hashing` - Password hashing (bcrypt, argon2)
- `marko/validation` - Request/data validation

**Optional - Database:**
- `marko/database` + driver (`marko/database-mysql` or `marko/database-pgsql`)

**Optional - Sessions:**
- `marko/session` + driver (`marko/session-file`)

**Optional - Views:**
- `marko/view` + driver (`marko/view-latte`)

**Optional - Caching:**
- `marko/cache` + driver (`marko/cache-file`)

**Optional - Authentication:**
- `marko/auth` (requires session for web auth)

**Optional - Email:**
- `marko/mail` + driver (`marko/mail-smtp`)

**Optional - Logging:**
- `marko/log` + driver (`marko/log-file`)

**Optional - Queue:**
- `marko/queue` + driver (`marko/queue-database`)

**Optional - Filesystem:**
- `marko/filesystem` + driver (`marko/filesystem-local`)

**Optional - Development:**
- `marko/errors-advanced` - Pretty error pages

### Installation Examples

**Full Web Application:**
```bash
composer require marko/framework \
    marko/database marko/database-pgsql \
    marko/session marko/session-file \
    marko/view marko/view-latte \
    marko/cache marko/cache-file \
    marko/auth \
    marko/mail marko/mail-smtp \
    marko/log marko/log-file \
    marko/errors-advanced
```

**Minimal API:**
```bash
composer require marko/framework \
    marko/database marko/database-mysql \
    marko/log marko/log-file
```

**Headless/CLI Application:**
```bash
composer require marko/core marko/cli marko/config marko/errors marko/errors-simple
```

### Version Strategy
- All packages follow semantic versioning
- Metapackage uses caret constraints (`^1.0`) for flexibility
- Major version bumps indicate breaking changes
- All packages in a major version are guaranteed compatible

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| **Dependency conflicts** | Test matrix ensures all package combinations work |
| **Bloated installs** | Keep metapackage minimal; extras via suggest |
| **Version drift** | Coordinated releases; CI tests all combinations |
| **Unclear what's included** | Clear README with package categories |
