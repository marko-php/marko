#!/usr/bin/env bash
set -euo pipefail

GITHUB_ORG="${GITHUB_ORG:-marko-php}"

# Prerequisites
command -v gh >/dev/null 2>&1 || { echo "Error: gh CLI not installed. https://cli.github.com"; exit 1; }
gh auth status >/dev/null 2>&1 || { echo "Error: Not authenticated with gh. Run: gh auth login"; exit 1; }

# Allow targeting a single repo or all child repos
TARGET_REPO="${1:-}"

if [[ -n "$TARGET_REPO" ]]; then
    repos=("$TARGET_REPO")
else
    mapfile -t repos < <(gh repo list "$GITHUB_ORG" --limit 200 --json name --jq '.[].name' | grep -v '^marko$' | sort)
fi

echo "Syncing split repo config for ${#repos[@]} repo(s)..."

for repo in "${repos[@]}"; do
    full_repo="${GITHUB_ORG}/${repo}"
    echo "  ${full_repo}..."

    # Configure repo settings (idempotent, safe on empty repos).
    gh api "repos/${full_repo}" \
        --method PATCH \
        --field has_projects=false \
        --field homepage="https://github.com/marko-php/marko" \
        --silent 2>/dev/null || true

    echo "    ✓ done"
done

echo "Done! All split repos synced."
