# Task 005: Issue Templates

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create YAML-format issue templates for bug reports and feature requests, plus a config.yml that allows blank issues. Templates auto-apply the appropriate label.

## Context
- Related files: `.github/ISSUE_TEMPLATE/bug_report.yml`, `.github/ISSUE_TEMPLATE/feature_request.yml`, `.github/ISSUE_TEMPLATE/config.yml` (all new)
- Uses GitHub's YAML form schema (not markdown templates)
- Package categories for dropdown: Admin, API, Authentication, Authorization, Blog, Cache, CLI, Config, Core, CORS, Database, Encryption, Env, Errors, Filesystem, Framework, Hashing, Health, HTTP, Log, Mail, Media, Notification, Pagination, PubSub, Queue, Rate Limiting, Routing, Scheduler, Search, Security, Session, SSE, Testing, Translation, Validation, View, Webhook, Other
- Keep templates clean and minimal — pragmatically opinionated

## Requirements (Test Descriptions)
- [x] `it creates bug_report.yml with description, steps to reproduce, expected and actual behavior fields`
- [x] `it creates bug_report.yml with package, PHP version, and Marko version fields`
- [x] `it auto-applies bug label on bug report`
- [x] `it creates feature_request.yml with problem, proposed solution, and alternatives fields`
- [x] `it creates feature_request.yml with package dropdown`
- [x] `it auto-applies enhancement label on feature request`
- [x] `it creates config.yml that allows blank issues`

## Acceptance Criteria
- Valid YAML form format (type: input, textarea, dropdown)
- All required fields marked as required
- Package dropdown covers all major package categories
- Templates are concise — no excessive instructions

## Implementation Notes
- Created `.github/ISSUE_TEMPLATE/bug_report.yml` with textarea fields for description, steps to reproduce, expected behavior, and actual behavior; dropdown for package; input fields for PHP version and Marko version; labels: [bug]
- Created `.github/ISSUE_TEMPLATE/feature_request.yml` with textarea fields for problem, proposed solution (required), and alternatives (optional); dropdown for package; labels: [enhancement]
- Created `.github/ISSUE_TEMPLATE/config.yml` with `blank_issues_enabled: true`
- Both templates use GitHub's YAML form schema (type: input, textarea, dropdown)
- All required fields marked with `required: true`
- Package dropdown covers all 39 categories listed in the task context
