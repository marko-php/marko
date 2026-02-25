# Task 008: Implement FrontendDetector

**Status**: completed
**Depends on**: 006
**Retry count**: 0

## Description
Create `FrontendDetector` that detects frontend tooling in the project. Checks for a `dev` script in `package.json` and determines the correct package manager to invoke it. This is intentionally tool-agnostic — it doesn't care if it's Tailwind, Vite, Webpack, or anything else.

## Context
- Related files: `packages/dev-server/src/Detection/FrontendDetector.php`
- Detection logic:
  1. Check if `package.json` exists in project root
  2. Parse it and check for `scripts.dev` key
  3. If `dev` script exists, determine package manager by lockfile:
     - `bun.lockb` → `bun run dev`
     - `pnpm-lock.yaml` → `pnpm run dev`
     - `yarn.lock` → `yarn dev`
     - `package-lock.json` or default → `npm run dev`
  4. Return the command string, or null if no frontend detected
- This replaces the earlier Tailwind-specific detection with a generic approach

## Requirements (Test Descriptions)
- [ ] `it detects dev script in package.json`
- [ ] `it returns null when package.json does not exist`
- [ ] `it returns null when package.json has no dev script`
- [ ] `it uses bun when bun.lockb exists`
- [ ] `it uses pnpm when pnpm-lock.yaml exists`
- [ ] `it uses yarn when yarn.lock exists`
- [ ] `it defaults to npm when no lockfile found`
- [ ] `it defaults to npm when only package-lock.json exists`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- Uses filesystem checks (testable with temp directories)
- No Tailwind-specific or tool-specific logic

## Implementation Notes
(Left blank - filled in by programmer during implementation)
