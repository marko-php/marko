# Task 005: Git Pre-Commit Hook

**Status**: completed
**Depends on**: 002, 003
**Retry count**: 0

## Description
Create the git pre-commit hook that automatically formats and validates PHP code before allowing commits. The hook runs php-cs-fixer, phpcbf, and phpcs in sequence, failing the commit if unfixable violations remain.

## Context
- Location: `/.githooks/pre-commit`
- Requires git config: `git config core.hooksPath .githooks`
- Only processes staged PHP files for performance

## Requirements (Test Descriptions)
- [ ] `it creates .githooks directory`
- [ ] `it creates executable pre-commit hook file`
- [ ] `it runs php-cs-fixer on staged PHP files`
- [ ] `it re-stages files after php-cs-fixer fixes`
- [ ] `it runs phpcbf on staged PHP files`
- [ ] `it runs phpcs validation as final check`
- [ ] `it fails commit when phpcs finds unfixable violations`
- [ ] `it provides helpful error message on validation failure`
- [ ] `it passes commit when all checks succeed`
- [ ] `it skips gracefully when no PHP files are staged`

## Acceptance Criteria
- All requirements have passing tests
- Hook is executable (chmod +x)
- Hook workflow auto-formats and validates correctly
- Clear error messages guide developers

## Files to Create
```
.githooks/
  pre-commit
```

## Hook Script
```bash
#!/bin/bash

# Get list of staged PHP files
STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep "\.php$")

if [ -z "$STAGED_FILES" ]; then
    exit 0
fi

# Run php-cs-fixer
./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php --quiet $STAGED_FILES

# Re-stage fixed files
echo "$STAGED_FILES" | xargs git add

# Run phpcbf (suppress errors as it returns non-zero when it fixes things)
./vendor/bin/phpcbf --standard=phpcs.xml $STAGED_FILES 2>/dev/null

# Re-stage fixed files
echo "$STAGED_FILES" | xargs git add

# Run phpcs for final validation
./vendor/bin/phpcs --standard=phpcs.xml $STAGED_FILES
PHPCS_EXIT=$?

if [ $PHPCS_EXIT -ne 0 ]; then
    echo ""
    echo "PHPCS found violations that couldn't be auto-fixed. Please fix manually."
    exit 1
fi

exit 0
```

## Setup Instructions
After creating the hook, configure git to use it:
```bash
git config core.hooksPath .githooks
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
