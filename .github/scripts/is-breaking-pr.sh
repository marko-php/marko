#!/usr/bin/env bash
# Prints "true" when a pull request declares a breaking change, else "false".
# Reads PR_TITLE and PR_BODY from the environment (as the auto-label workflow passes them).
#
# Follows the Conventional Commits rules only:
#   - a "!" after the type in the title:      feat!: ... / refactor(cache)!: ...
#   - a footer line in the body that starts:  BREAKING CHANGE: ... / BREAKING-CHANGE: ...
# Prose that merely mentions breaking changes ("## Breaking changes\n\nNone.") is not a
# declaration, so it never adds the label.
set -euo pipefail

title="${PR_TITLE:-}"
body="${PR_BODY:-}"

if printf '%s\n' "$title" | grep -qiE '^[a-z]+(\([^)]*\))?!:'; then
    echo true
    exit 0
fi

if printf '%s\n' "$body" | tr -d '\r' | grep -qE '^BREAKING[ -]CHANGE:'; then
    echo true
    exit 0
fi

echo false
