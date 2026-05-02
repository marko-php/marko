#!/usr/bin/env bash
set -euo pipefail

GITHUB_ORG="${GITHUB_ORG:-marko-php}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"

usage() {
    cat <<EOF
Usage: $(basename "$0") [--dry-run] [package-name ...]

Creates split repos under \${GITHUB_ORG} (default: marko-php) for Marko packages.

If no package names are given, every directory under packages/ is checked.
If package names are given (without the "marko-" prefix), only those are checked.

The script fetches the org's existing repos with a single API call and
compares locally, so a no-op run completes in well under a second even
with 70+ packages.

Options:
  --dry-run    Print what would be created without making any API calls.
  -h, --help   Show this message.

Examples:
  $(basename "$0")                              # check all packages
  $(basename "$0") inertia-react inertia-vue    # check just those two
  $(basename "$0") --dry-run                    # preview only

EOF
}

# Parse args
DRY_RUN=false
PACKAGES=()
for arg in "$@"; do
    case "$arg" in
        --dry-run) DRY_RUN=true ;;
        -h|--help) usage; exit 0 ;;
        --*) echo "Error: unknown flag '$arg'" >&2; usage >&2; exit 1 ;;
        *) PACKAGES+=("$arg") ;;
    esac
done

# Prerequisites
command -v gh >/dev/null 2>&1 || { echo "Error: gh CLI not installed. https://cli.github.com" >&2; exit 1; }
command -v jq >/dev/null 2>&1 || { echo "Error: jq not installed. brew install jq" >&2; exit 1; }
gh auth status >/dev/null 2>&1 || { echo "Error: Not authenticated with gh. Run: gh auth login" >&2; exit 1; }

# Default to every package directory if no args given.
if [ ${#PACKAGES[@]} -eq 0 ]; then
    for pkg_dir in "$REPO_ROOT"/packages/*/; do
        PACKAGES+=("$(basename "$pkg_dir")")
    done
fi

# Single API call to list every repo in the org. Compared locally below — much
# faster than N sequential `gh repo view` calls (the previous behavior).
echo "Fetching existing repos under ${GITHUB_ORG}..."
EXISTING_REPOS=$(gh repo list "$GITHUB_ORG" --limit 1000 --json name --jq '.[].name')

if [ "$DRY_RUN" = true ]; then
    echo "(dry run — no repos will be created)"
fi

CREATED=0
SKIPPED=0
MISSING_DIR=0
for pkg in "${PACKAGES[@]}"; do
    repo_name="marko-${pkg}"
    full_repo="${GITHUB_ORG}/${repo_name}"

    # Already exists — skip silently to keep output focused on actual work.
    if echo "$EXISTING_REPOS" | grep -qx "$repo_name"; then
        SKIPPED=$((SKIPPED + 1))
        continue
    fi

    pkg_dir="$REPO_ROOT/packages/$pkg"
    if [ ! -d "$pkg_dir" ]; then
        echo "  ⚠ packages/${pkg} does not exist — skipping" >&2
        MISSING_DIR=$((MISSING_DIR + 1))
        continue
    fi

    description="[READ-ONLY] Subtree split of marko/${pkg}. Issues and PRs at https://github.com/marko-php/marko"

    if [[ -f "$pkg_dir/composer.json" ]]; then
        pkg_description=$(jq -r '.description // empty' "$pkg_dir/composer.json")
        [[ -n "$pkg_description" ]] && description="[READ-ONLY] ${pkg_description}. Issues and PRs at https://github.com/marko-php/marko"
    fi

    if [ "$DRY_RUN" = true ]; then
        echo "  Would create ${full_repo}"
        echo "    description: ${description}"
        CREATED=$((CREATED + 1))
        continue
    fi

    echo "  Creating ${full_repo}..."
    gh repo create "$full_repo" \
        --public \
        --description "$description" \
        --disable-issues \
        --disable-wiki

    "$SCRIPT_DIR/sync-split-repo-config.sh" "${repo_name}"

    echo "  ✓ Created ${full_repo}"
    CREATED=$((CREATED + 1))
done

echo ""
if [ "$DRY_RUN" = true ]; then
    echo "Dry run done: ${CREATED} would be created, ${SKIPPED} already exist, ${MISSING_DIR} skipped (no local dir)."
else
    echo "Done: ${CREATED} created, ${SKIPPED} already existed, ${MISSING_DIR} skipped (no local dir)."
fi
