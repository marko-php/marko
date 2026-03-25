#!/usr/bin/env bash
set -euo pipefail

GITHUB_ORG="${GITHUB_ORG:-marko-php}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WORKFLOW_FILE="$SCRIPT_DIR/split-repo-workflow.yml"

# Prerequisites
command -v gh >/dev/null 2>&1 || { echo "Error: gh CLI not installed. https://cli.github.com"; exit 1; }
gh auth status >/dev/null 2>&1 || { echo "Error: Not authenticated with gh. Run: gh auth login"; exit 1; }

[[ -f "$WORKFLOW_FILE" ]] || { echo "Error: $WORKFLOW_FILE not found"; exit 1; }

# Allow targeting a single repo or all child repos
TARGET_REPO="${1:-}"

if [[ -n "$TARGET_REPO" ]]; then
    repos=("$TARGET_REPO")
else
    mapfile -t repos < <(gh repo list "$GITHUB_ORG" --limit 200 --json name --jq '.[].name' | grep -v '^marko$' | sort)
fi

WORKFLOW_CONTENT=$(base64 < "$WORKFLOW_FILE")
WORKFLOW_PATH=".github/workflows/close-pull-requests.yml"

echo "Syncing split repo config for ${#repos[@]} repo(s)..."

for repo in "${repos[@]}"; do
    full_repo="${GITHUB_ORG}/${repo}"
    echo "  ${full_repo}..."

    # Configure repo settings (idempotent)
    gh api "repos/${full_repo}" \
        --method PATCH \
        --field has_projects=false \
        --field homepage="https://github.com/marko-php/marko" \
        --silent 2>/dev/null || true

    # Push the workflow file (create or update)
    existing_sha=$(gh api "repos/${full_repo}/contents/${WORKFLOW_PATH}" --jq '.sha' 2>/dev/null || echo "")

    if [[ -n "$existing_sha" ]]; then
        gh api "repos/${full_repo}/contents/${WORKFLOW_PATH}" \
            --method PUT \
            --field message="chore: update close-pull-requests workflow" \
            --field content="$WORKFLOW_CONTENT" \
            --field sha="$existing_sha" \
            --silent
    else
        gh api "repos/${full_repo}/contents/${WORKFLOW_PATH}" \
            --method PUT \
            --field message="chore: add close-pull-requests workflow" \
            --field content="$WORKFLOW_CONTENT" \
            --silent
    fi

    echo "    ✓ done"
done

echo "Done! All split repos synced."
