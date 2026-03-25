#!/usr/bin/env bash
set -euo pipefail

GITHUB_ORG="${GITHUB_ORG:-marko-php}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"

# Prerequisites
command -v gh >/dev/null 2>&1 || { echo "Error: gh CLI not installed. https://cli.github.com"; exit 1; }
command -v jq >/dev/null 2>&1 || { echo "Error: jq not installed. brew install jq"; exit 1; }
gh auth status >/dev/null 2>&1 || { echo "Error: Not authenticated with gh. Run: gh auth login"; exit 1; }

echo "Creating split repos under ${GITHUB_ORG}..."

for pkg_dir in "$REPO_ROOT"/packages/*/; do
    pkg=$(basename "$pkg_dir")
    repo="${GITHUB_ORG}/marko-${pkg}"
    description="[READ-ONLY] Subtree split of marko/${pkg}. Issues and PRs at https://github.com/marko-php/marko"

    # Get description from composer.json
    if [[ -f "$pkg_dir/composer.json" ]]; then
        pkg_description=$(jq -r '.description // empty' "$pkg_dir/composer.json")
        [[ -n "$pkg_description" ]] && description="[READ-ONLY] ${pkg_description}. Issues and PRs at https://github.com/marko-php/marko"
    fi

    if gh repo view "$repo" >/dev/null 2>&1; then
        echo "  ✓ ${repo} already exists, skipping"
    else
        echo "  Creating ${repo}..."
        gh repo create "$repo" \
            --public \
            --description "$description" \
            --disable-issues \
            --disable-wiki

        # Sync repo settings and workflow
        "$SCRIPT_DIR/sync-split-repo-config.sh" "marko-${pkg}"

        echo "  ✓ Created ${repo}"
    fi
done

echo "Done! All split repos created under ${GITHUB_ORG}."
