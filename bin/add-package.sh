#!/usr/bin/env bash
set -euo pipefail

PACKAGE="${1:?Usage: ./bin/add-package.sh <package-name> (e.g., new-feature)}"
GITHUB_ORG="${GITHUB_ORG:-marko-php}"
PACKAGIST_USERNAME="${PACKAGIST_USERNAME:?Error: PACKAGIST_USERNAME is required}"
PACKAGIST_TOKEN="${PACKAGIST_TOKEN:?Error: PACKAGIST_TOKEN is required}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"

command -v gh >/dev/null 2>&1 || { echo "Error: gh CLI not installed"; exit 1; }
command -v jq >/dev/null 2>&1 || { echo "Error: jq not installed. brew install jq"; exit 1; }
command -v curl >/dev/null 2>&1 || { echo "Error: curl not installed"; exit 1; }

PKG_DIR="$REPO_ROOT/packages/$PACKAGE"
[[ -d "$PKG_DIR" ]] || { echo "Error: packages/${PACKAGE}/ does not exist"; exit 1; }
[[ -f "$PKG_DIR/composer.json" ]] || { echo "Error: packages/${PACKAGE}/composer.json does not exist"; exit 1; }

echo "Adding package: marko/${PACKAGE}"

# Step 1: Create split repo
REPO="${GITHUB_ORG}/marko-${PACKAGE}"
if gh repo view "$REPO" >/dev/null 2>&1; then
    echo "  ✓ Split repo ${REPO} already exists"
else
    pkg_description=$(jq -r '.description // empty' "$PKG_DIR/composer.json")
    description="[READ-ONLY] ${pkg_description:-Subtree split of marko/${PACKAGE}}. Issues and PRs at https://github.com/marko-php/marko"
    gh repo create "$REPO" --public --description "$description" --disable-issues --disable-wiki
    echo "  ✓ Created split repo ${REPO}"
fi

# Step 2: Register on Packagist
REPO_URL="https://github.com/${REPO}"
response=$(curl -s -o /dev/null -w "%{http_code}" \
    -X POST \
    "https://packagist.org/api/create-package?username=${PACKAGIST_USERNAME}&apiToken=${PACKAGIST_TOKEN}" \
    -H "Content-Type: application/json" \
    -d "{\"repository\":{\"url\":\"${REPO_URL}\"}}")

if [[ "$response" == "200" || "$response" == "201" ]]; then
    echo "  ✓ Registered on Packagist"
elif [[ "$response" == "400" ]]; then
    echo "  ⚠ Already registered on Packagist (HTTP 400)"
else
    echo "  ✗ Packagist registration failed (HTTP ${response})"
fi

# Step 3: Update root composer.json
ROOT_COMPOSER="$REPO_ROOT/composer.json"
PKG_TYPE=$(jq -r '.type // "library"' "$PKG_DIR/composer.json")
PKG_PATH="packages/${PACKAGE}"
echo "  Updating root composer.json..."

# Check if repository entry already exists
if jq -e --arg path "$PKG_PATH" '.repositories[] | select(.url == $path)' "$ROOT_COMPOSER" >/dev/null 2>&1; then
    REPO_EXISTS=true
else
    REPO_EXISTS=false
fi

if [[ "$PKG_TYPE" == "project" ]]; then
    # Project-type packages (e.g., skeleton) are used via create-project, not require'd as dependencies.
    # They only need a path repository entry — no require or replace.
    if [[ "$REPO_EXISTS" == "true" ]]; then
        echo "  ✓ Root composer.json already has repository entry (type:project — no require/replace needed)"
    else
        jq --indent 4 \
            --arg path "$PKG_PATH" \
            '.repositories += [{"type": "path", "url": $path}]' \
            "$ROOT_COMPOSER" > "${ROOT_COMPOSER}.tmp" && mv "${ROOT_COMPOSER}.tmp" "$ROOT_COMPOSER"
        echo "  ✓ Updated root composer.json (path repository only — type:project skips require/replace)"
    fi
else
    if [[ "$REPO_EXISTS" == "true" ]] && jq -e --arg pkg "marko/${PACKAGE}" '.require[$pkg]' "$ROOT_COMPOSER" >/dev/null 2>&1; then
        echo "  ✓ Root composer.json already has entries for marko/${PACKAGE}"
    else
        jq --indent 4 \
            --arg pkg "marko/${PACKAGE}" \
            --arg path "$PKG_PATH" \
            '(.repositories // []) as $repos |
            if ($repos | map(select(.url == $path)) | length) > 0
            then .require[$pkg] = "self.version" | .replace[$pkg] = "self.version"
            else .require[$pkg] = "self.version" | .replace[$pkg] = "self.version" | .repositories += [{"type": "path", "url": $path}]
            end' \
            "$ROOT_COMPOSER" > "${ROOT_COMPOSER}.tmp" && mv "${ROOT_COMPOSER}.tmp" "$ROOT_COMPOSER"
        echo "  ✓ Updated root composer.json"
    fi
fi

echo ""
echo "Done! Next steps:"
echo "  1. Add PSR-4 autoload entry to packages/${PACKAGE}/composer.json if not already present"
echo "  2. Commit and push to trigger the split workflow"
echo "  3. The split workflow will push to ${REPO} and Packagist will auto-update"
