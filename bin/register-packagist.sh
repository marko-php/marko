#!/usr/bin/env bash
# Usage: ./bin/register-packagist.sh [package ...]
#   With no arguments, registers every package in packages/.
#   With arguments (e.g. `redis forms`), registers only those packages.
set -euo pipefail

GITHUB_ORG="${GITHUB_ORG:-marko-php}"
PACKAGIST_USERNAME="${PACKAGIST_USERNAME:?Error: PACKAGIST_USERNAME is required}"
PACKAGIST_TOKEN="${PACKAGIST_TOKEN:?Error: PACKAGIST_TOKEN is required}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"

command -v curl >/dev/null 2>&1 || { echo "Error: curl not installed"; exit 1; }
command -v jq >/dev/null 2>&1 || { echo "Error: jq not installed. brew install jq"; exit 1; }

if [[ $# -gt 0 ]]; then
    # Validate every name before registering anything, so a typo never leaves a partial run.
    pkg_dirs=()
    for pkg in "$@"; do
        [[ -f "$REPO_ROOT/packages/${pkg}/composer.json" ]] || {
            echo "Error: packages/${pkg}/ does not exist (or has no composer.json)"
            exit 1
        }
        pkg_dirs+=("$REPO_ROOT/packages/${pkg}/")
    done
else
    pkg_dirs=("$REPO_ROOT"/packages/*/)
fi

echo "Registering packages on Packagist under ${GITHUB_ORG}..."

for pkg_dir in "${pkg_dirs[@]}"; do
    pkg=$(basename "$pkg_dir")
    repo_url="https://github.com/${GITHUB_ORG}/marko-${pkg}"

    echo "  Registering marko/${pkg} (${repo_url})..."

    response=$(curl -s -o /dev/null -w "%{http_code}" \
        -X POST \
        "https://packagist.org/api/create-package" \
        -H "Content-Type: application/json" \
        -d "{\"repository\":{\"url\":\"${repo_url}\"}}" \
        --config - <<CURL_CONFIG
header = "Authorization: Bearer ${PACKAGIST_USERNAME}:${PACKAGIST_TOKEN}"
CURL_CONFIG
)

    if [[ "$response" == "200" || "$response" == "201" || "$response" == "202" ]]; then
        echo "  ✓ Registered marko/${pkg}"
    elif [[ "$response" == "400" ]]; then
        echo "  ⚠ marko/${pkg} may already be registered (HTTP 400) — skipping"
    else
        echo "  ✗ Failed to register marko/${pkg} (HTTP ${response})"
    fi
done

echo "Done! Check https://packagist.org/packages/marko/ for registered packages."
