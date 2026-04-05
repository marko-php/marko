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
PHP_BIN="${PHP_BIN:-php}"

PHP_VERSION=$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
if [[ "$PHP_VERSION" != "8.5" ]]; then
    echo "Error: PHP 8.5+ required (found ${PHP_VERSION}). Set PHP_BIN or install PHP 8.5."
    exit 1
fi

# Check for gh CLI availability
GH_AVAILABLE=false
if command -v gh >/dev/null 2>&1; then
    GH_AVAILABLE=true
else
    echo "  ⚠ gh CLI not found — GitHub Release will need to be created manually"
fi

echo "  ✓ PHP ${PHP_VERSION}"
echo "  ✓ Branch: main (merged from develop)"
echo "  ✓ Working directory clean"
echo "  ✓ Tag ${TAG} available"
echo ""
echo "Running test suite..."

"$PHP_BIN" vendor/bin/pest --parallel --exclude-group=integration-destructive || {
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

# Create GitHub Release
if [[ "$GH_AVAILABLE" == "true" ]]; then
    echo "Creating GitHub Release for ${TAG}..."

    PREV_TAG=""
    if PREV_TAG_CANDIDATE=$(git describe --tags --abbrev=0 HEAD^ 2>/dev/null); then
        PREV_TAG="$PREV_TAG_CANDIDATE"
    fi

    if [[ -n "$PREV_TAG" ]]; then
        if gh release create "$TAG" \
            --generate-notes \
            --latest \
            --notes-start-tag "$PREV_TAG"; then
            echo "  ✓ GitHub Release created"
        else
            echo "  ⚠ GitHub Release creation failed — create it manually at https://github.com/marko-php/marko/releases/new"
        fi
    else
        if gh release create "$TAG" \
            --generate-notes \
            --latest; then
            echo "  ✓ GitHub Release created"
        else
            echo "  ⚠ GitHub Release creation failed — create it manually at https://github.com/marko-php/marko/releases/new"
        fi
    fi
else
    echo "  ⚠ Skipping GitHub Release creation (gh CLI not available)"
fi

# Return to develop branch
git checkout develop
git merge main
git push origin develop

echo ""
echo "✓ Released ${TAG}!"
echo ""
echo "Next steps (automatic):"
echo "  1. GitHub Release ${TAG} published with auto-generated notes"
echo "  2. GitHub Actions split workflow will split all packages"
echo "  3. Each split repo will be tagged with ${TAG}"
echo "  4. Packagist will auto-update via webhooks"
echo ""
echo "Monitor progress: https://github.com/marko-php/marko/actions"
