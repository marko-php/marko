# Task 006: Update dev-server docs and README

**Status**: completed
**Depends on**: 003, 004, 005
**Retry count**: 0

## Description
Update the dev-server documentation page and package README to reflect the new default detached mode, the `--foreground`/`-f` flag, and the `public/index.php` guard behavior.

## Context
- Related files: `docs/src/content/docs/packages/dev-server.md`, `packages/dev-server/README.md`
- The docs page has a comprehensive API reference and configuration table
- The README is slim (title, quick example, link to docs)
- Changes needed: default detach is now true, new `--foreground`/`-f` flag, guard behavior

## Requirements (Test Descriptions)
- [ ] `it updates dev-server.md to show detach default as true`
- [ ] `it documents the --foreground/-f flag in dev-server.md`
- [ ] `it documents the public/index.php guard behavior in dev-server.md`
- [ ] `it updates the README quick example to reflect detached default`

## Acceptance Criteria
- Docs page config table shows `detach` default as `true`
- `--foreground`/`-f` flag is documented
- Guard behavior (error when `public/index.php` missing) is mentioned
- README quick example is current
- No broken markdown

## Implementation Notes
### dev-server.md changes:
1. Config table: change `detach` default from `false` to `true`
2. Add `--foreground`/`-f` to the CLI flags section
3. Update the "Detached Mode" section to explain it's now default
4. Add a note about the `public/index.php` requirement

### README changes:
1. Update quick example — `marko up` now runs detached by default, no `-d` needed
2. Add note about `marko up -f` for foreground mode
