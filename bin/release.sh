#!/usr/bin/env bash
set -euo pipefail

VERSION="${1:?Usage: ./bin/release.sh <version> (e.g., 0.1.0)}"
TAG="${VERSION}"

echo "Preparing release ${TAG}..."

# Validate semver format (X.Y.Z — no v prefix, no pre-release suffix)
if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "Error: Invalid version format '${VERSION}'. Expected X.Y.Z (e.g., 0.1.0)"
    exit 1
fi

# Validate clean working directory
if ! git diff --quiet || ! git diff --cached --quiet; then
    echo "Error: Working directory has uncommitted changes. Commit or stash them first."
    git status --short
    exit 1
fi

# Checkout main and merge develop
echo "Merging develop into main..."
git checkout main
git pull origin main
git merge develop

# Validate tag doesn't already exist
if git rev-parse "$TAG" >/dev/null 2>&1; then
    echo "Error: Tag ${TAG} already exists. Did you mean a different version?"
    exit 1
fi

# Find PHP 8.5+ binary
PHP_BIN="/opt/homebrew/Cellar/php/8.5.1_2/bin/php"
if [[ ! -x "$PHP_BIN" ]]; then
    PHP_BIN="php"
fi

PHP_VERSION=$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
if [[ "$PHP_VERSION" != "8.5" ]]; then
    echo "Error: PHP 8.5+ required (found ${PHP_VERSION}). Set PHP_BIN or install PHP 8.5."
    exit 1
fi

echo "  ✓ PHP ${PHP_VERSION}"
echo "  ✓ Branch: ${CURRENT_BRANCH}"
echo "  ✓ Working directory clean"
echo "  ✓ Tag ${TAG} available"
echo ""
echo "Running test suite..."

"$PHP_BIN" vendor/bin/pest --parallel || {
    echo ""
    echo "Error: Tests failed. Fix failing tests before releasing."
    exit 1
}

echo ""
echo "  ✓ All tests passing"
echo ""
echo "Pushing main branch..."
git push origin main

echo "Creating tag ${TAG}..."
git tag -a "$TAG" -m "Release ${VERSION}"
git push origin "$TAG"

# Return to develop branch
git checkout develop
git merge main

echo ""
echo "✓ Released ${TAG}!"
echo ""
echo "Next steps (automatic):"
echo "  1. GitHub Actions split workflow will split all packages"
echo "  2. Each split repo will be tagged with ${TAG}"
echo "  3. Packagist will auto-update via webhooks"
echo ""
echo "Monitor progress: https://github.com/marko-php/marko/actions"
