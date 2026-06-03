#!/usr/bin/env bash
#
# check-readme-packages.sh
#
# Asserts that the root README.md package catalog is in sync with the
# `packages/` directory. Every Composer package with `type: marko-module`
# under `packages/` must have a row in the catalog, and every catalog row
# must reference a real package directory.
#
# Exits 0 on success. Exits 1 with a human-readable diff on drift.
#
# Run locally:   bin/check-readme-packages.sh
# Run in CI:     same — invoked from .github/workflows/readme-package-check.yml
#
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

README="README.md"
PACKAGES_DIR="packages"

if [[ ! -f "$README" ]]; then
    echo "Error: $README not found at repo root." >&2
    exit 2
fi

if [[ ! -d "$PACKAGES_DIR" ]]; then
    echo "Error: $PACKAGES_DIR/ directory not found at repo root." >&2
    exit 2
fi

if ! command -v jq >/dev/null 2>&1; then
    echo "Error: jq is required. Install with: brew install jq" >&2
    exit 2
fi

# Collect every package basename under packages/ that is not a project template.
# `type: project` (currently just marko/skeleton) is the only kind that legitimately
# stays out of the README catalog — everything else is a module of some flavor
# (type: library, marko-module, metapackage, or unset).
mapfile -t MODULE_PKGS < <(
    for composer in "$PACKAGES_DIR"/*/composer.json; do
        [[ -f "$composer" ]] || continue
        type=$(jq -r '.type // ""' "$composer")
        if [[ "$type" != "project" ]]; then
            basename "$(dirname "$composer")"
        fi
    done | sort -u
)

# Scrape catalog entries from README. Matches `packages/<name>/README.md` links.
mapfile -t README_PKGS < <(
    grep -oE 'packages/[a-z0-9][a-z0-9-]*/README\.md' "$README" \
        | sed -E 's|packages/([^/]+)/README\.md|\1|' \
        | sort -u
)

# Diff both directions.
missing_from_readme=()
for pkg in "${MODULE_PKGS[@]}"; do
    if ! printf '%s\n' "${README_PKGS[@]}" | grep -qxF "$pkg"; then
        missing_from_readme+=("$pkg")
    fi
done

stale_in_readme=()
for pkg in "${README_PKGS[@]}"; do
    if ! printf '%s\n' "${MODULE_PKGS[@]}" | grep -qxF "$pkg"; then
        stale_in_readme+=("$pkg")
    fi
done

if [[ ${#missing_from_readme[@]} -eq 0 && ${#stale_in_readme[@]} -eq 0 ]]; then
    echo "README.md package catalog is aligned with packages/ (${#MODULE_PKGS[@]} modules)."
    exit 0
fi

echo "README.md package catalog is out of sync with packages/." >&2
echo >&2

if [[ ${#missing_from_readme[@]} -gt 0 ]]; then
    echo "Packages present under packages/ but missing from README.md:" >&2
    for pkg in "${missing_from_readme[@]}"; do
        echo "  - $pkg" >&2
    done
    echo >&2
    echo "  Fix: add a row to the appropriate section of README.md, e.g." >&2
    echo "       | [${missing_from_readme[0]}](packages/${missing_from_readme[0]}/README.md) | <description> |" >&2
    echo >&2
fi

if [[ ${#stale_in_readme[@]} -gt 0 ]]; then
    echo "Packages referenced in README.md but not present under packages/ (or type: project):" >&2
    for pkg in "${stale_in_readme[@]}"; do
        echo "  - $pkg" >&2
    done
    echo >&2
    echo "  Fix: remove the stale row from README.md, or restore the package." >&2
    echo >&2
fi

exit 1
