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

# gh CLI is required to generate release notes for CHANGELOG.md and create the GitHub Release.
GH_AVAILABLE=false
if command -v gh >/dev/null 2>&1; then
    GH_AVAILABLE=true
else
    echo "Error: gh CLI not found — required to generate release notes. Install with: brew install gh"
    exit 1
fi

# Derive repo from git remote so gh doesn't require set-default
GH_REPO=$(git remote get-url origin | sed -E 's#.*github\.com[:/](.+)\.git$#\1#; s#.*github\.com[:/](.+)$#\1#')

echo "  ✓ PHP ${PHP_VERSION}"
echo "  ✓ Branch: main (merged from develop)"
echo "  ✓ Working directory clean"
echo "  ✓ Tag ${TAG} available"
echo ""
echo "Running test suite (including integration-destructive group to verify clean install)..."

"$PHP_BIN" vendor/bin/pest --parallel || {
    echo ""
    echo "Error: Tests failed. Fix failing tests before releasing."
    exit 1
}

echo ""
echo "  ✓ All tests passing"
echo ""

# Push main to remote BEFORE generating release notes — the GitHub
# `releases/generate-notes` API enumerates PRs by walking commits visible
# on the remote, so any merges that haven't been pushed yet are silently
# omitted. Push first, then generate notes, then commit the changelog and
# push again with the tag.
echo "Pushing main branch (so generate-notes sees all merge commits)..."
git push origin main
echo ""

# Update CHANGELOG.md (requires gh + jq)
CHANGELOG_FILE="CHANGELOG.md"
CHANGELOG_MARKER="<!-- new-entries-below — do not remove this marker; bin/release.sh inserts new versions directly below it -->"
NOTES_FILE=""

if [[ "$GH_AVAILABLE" != "true" ]]; then
    echo "Error: gh CLI is required to generate release notes for CHANGELOG.md."
    exit 1
fi

if ! command -v jq >/dev/null 2>&1; then
    echo "Error: jq is required to generate release notes. Install with: brew install jq"
    exit 1
fi

if [[ ! -f "$CHANGELOG_FILE" ]]; then
    echo "Error: $CHANGELOG_FILE not found at repo root."
    exit 1
fi

if ! grep -qF "$CHANGELOG_MARKER" "$CHANGELOG_FILE"; then
    echo "Error: $CHANGELOG_FILE is missing the insertion marker."
    echo "Expected line: $CHANGELOG_MARKER"
    exit 1
fi

PREV_TAG=""
if PREV_TAG_CANDIDATE=$(git describe --tags --abbrev=0 2>/dev/null); then
    PREV_TAG="$PREV_TAG_CANDIDATE"
fi

echo "Generating release notes via gh API (previous tag: ${PREV_TAG:-none})..."
NOTES_FILE=$(mktemp)
trap 'rm -f "$NOTES_FILE"' EXIT

if [[ -n "$PREV_TAG" ]]; then
    gh api -X POST "repos/$GH_REPO/releases/generate-notes" \
        -f tag_name="$TAG" \
        -f previous_tag_name="$PREV_TAG" \
        -f target_commitish=main \
        --jq .body > "$NOTES_FILE"
else
    gh api -X POST "repos/$GH_REPO/releases/generate-notes" \
        -f tag_name="$TAG" \
        -f target_commitish=main \
        --jq .body > "$NOTES_FILE"
fi

if [[ ! -s "$NOTES_FILE" ]]; then
    echo "Error: gh API returned empty release notes."
    exit 1
fi

# Build CHANGELOG section: strip GitHub-Release-specific framing (HTML comment, "What's Changed"
# heading, and "Full Changelog" footer link). Keep category subheadings (### Bug Fixes, etc.)
# and PR/contributor lines.
TODAY=$(date +%Y-%m-%d)
CHANGELOG_SECTION_FILE=$(mktemp)
trap 'rm -f "$NOTES_FILE" "$CHANGELOG_SECTION_FILE"' EXIT

{
    echo "## [${VERSION}] - ${TODAY}"
    echo ""
    sed -E \
        -e '/^<!-- Release notes generated/d' \
        -e "/^## What'?s Changed$/d" \
        -e '/^\*\*Full Changelog\*\*:/d' \
        "$NOTES_FILE" \
    | awk 'NF {p=1} p {print}' \
    | awk '{lines[NR]=$0} END {n=NR; while (n>0 && lines[n]=="") n--; for (i=1;i<=n;i++) print lines[i]}'
    echo ""
} > "$CHANGELOG_SECTION_FILE"

echo "Prepending entry to ${CHANGELOG_FILE}..."
CHANGELOG_TMP=$(mktemp)
trap 'rm -f "$NOTES_FILE" "$CHANGELOG_SECTION_FILE" "$CHANGELOG_TMP"' EXIT

awk -v marker="$CHANGELOG_MARKER" -v section_file="$CHANGELOG_SECTION_FILE" '
    {
        print
        if (!inserted && index($0, marker) > 0) {
            print ""
            while ((getline line < section_file) > 0) print line
            close(section_file)
            inserted = 1
        }
    }
' "$CHANGELOG_FILE" > "$CHANGELOG_TMP"

mv "$CHANGELOG_TMP" "$CHANGELOG_FILE"

echo "  ✓ ${CHANGELOG_FILE} updated"
echo ""
echo "Committing changelog..."
git add "$CHANGELOG_FILE"
git commit -m "chore: changelog for ${VERSION}"

echo "Pushing main branch with changelog commit..."
git push origin main

echo "Creating tag ${TAG}..."
git tag -a "$TAG" -m "Release ${VERSION}"
git push origin "$TAG"

# Create GitHub Release using the same notes we wrote to CHANGELOG.md (single source of truth).
echo "Creating GitHub Release for ${TAG}..."
if gh release create "$TAG" \
    --repo "$GH_REPO" \
    --notes-file "$NOTES_FILE" \
    --latest; then
    echo "  ✓ GitHub Release created"
else
    echo "  ⚠ GitHub Release creation failed — create it manually at https://github.com/marko-php/marko/releases/new"
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
