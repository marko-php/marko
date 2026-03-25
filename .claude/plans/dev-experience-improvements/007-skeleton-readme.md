# Task 007: Create skeleton package README

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Create a full README.md for the skeleton package. Since there's no docs page for the skeleton, the README serves as the primary documentation.

## Context
- Related files: `packages/skeleton/README.md` (new)
- No docs page exists for skeleton — README should be comprehensive
- Follow `.claude/code-standards.md` "Package README Standards" for fuller READMEs
- The skeleton is the first thing new users see — it should be welcoming and clear

## Requirements (Test Descriptions)
- [ ] `it has a README.md file`
- [ ] `it includes installation via composer create-project`
- [ ] `it includes project structure overview`
- [ ] `it includes next steps for creating first controller`
- [ ] `it links to the getting-started docs`

## Acceptance Criteria
- README exists at `packages/skeleton/README.md`
- Covers: what it is, how to install, what's included, next steps
- Links to marko.build docs for deeper content
- Markdown is clean and well-structured

## Implementation Notes
Structure:
```markdown
# Marko Skeleton

Application skeleton for the Marko Framework.

## Installation

\`\`\`bash
composer create-project marko/skeleton my-app
cd my-app
\`\`\`

## What's Included

- `public/index.php` — Web entry point
- `app/` — Your application modules
- `modules/` — Third-party modules
- `config/` — Root configuration
- `storage/` — Logs, cache, sessions
- `.env.example` — Environment template

## Getting Started

1. Copy `.env.example` to `.env`
2. Start the dev server: `marko up`
3. Visit http://localhost:8000

## Next Steps

- [Your First Application](https://marko.build/docs/getting-started/first-application/)
- [Project Structure](https://marko.build/docs/getting-started/project-structure/)
- [Documentation](https://marko.build/docs/)
```
