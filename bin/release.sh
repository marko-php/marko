#!/usr/bin/env bash
set -euo pipefail

VERSION="${1:?Usage: ./bin/release.sh <version> (e.g., 0.1.0)}"
TAG="${VERSION}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

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

# Update CHANGELOG.md (requires gh + jq).
# With the deterministic generator below we no longer need to pre-push
# main — git log reads from the local repo and PR data is fetched by
# number, so nothing depends on remote main being current.
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

# Generate release notes deterministically from git history.
#
# Why we don't use `gh api releases/generate-notes`: that API matches PRs to
# the tag range by their stored `merge_commit_sha`, which can go stale when
# multiple PRs merge in close succession (GitHub leaves the field pointing
# at an old test-merge commit instead of the actual one). When that happens
# the API silently drops the PR with no warning. We hit this on 0.4.0 when
# PR #42 was omitted.
#
# Walking git log ourselves and looking each PR up by number sidesteps the
# entire SHA-matching dance. Categories below are kept in lockstep with
# `.github/release.yml` — add a category there, add it here too.
PREV_TAG=""
if PREV_TAG_CANDIDATE=$(git describe --tags --abbrev=0 2>/dev/null); then
    PREV_TAG="$PREV_TAG_CANDIDATE"
fi

if [[ -z "$PREV_TAG" ]]; then
    echo "Error: no previous tag found. Cannot determine release range."
    exit 1
fi

echo "Generating release notes from git history (range: ${PREV_TAG}..HEAD)..."

PR_NUMBERS=$(git log --oneline "${PREV_TAG}..HEAD" | grep -oE '#[0-9]+' | tr -d '#' | sort -n -u)

if [[ -z "$PR_NUMBERS" ]]; then
    echo "Error: no merged PRs found between ${PREV_TAG} and HEAD."
    echo "Every release-worthy commit must reference its PR with #NN in the message."
    exit 1
fi

PR_COUNT=$(echo "$PR_NUMBERS" | wc -l | tr -d ' ')
echo "  Found ${PR_COUNT} PR(s): $(echo "$PR_NUMBERS" | tr '\n' ' ')"

# Category order = output order. Format: label_name|section_title
# Mirror of `.github/release.yml` — keep in sync.
CATEGORIES=(
    "breaking|Breaking Changes"
    "enhancement|New Features"
    "bug|Bug Fixes"
    "documentation|Documentation"
    "refactor|Refactoring"
    "testing|Testing"
    "ci|CI"
    "maintenance|Maintenance"
)

PR_DATA_DIR=$(mktemp -d)
NOTES_FILE=$(mktemp)
CHANGELOG_SECTION_FILE=$(mktemp)
trap 'rm -rf "$PR_DATA_DIR"; rm -f "$NOTES_FILE" "$CHANGELOG_SECTION_FILE"' EXIT

OTHER_FILE="${PR_DATA_DIR}/other"
AUTHORS_FILE="${PR_DATA_DIR}/authors"
: > "$AUTHORS_FILE"

for num in $PR_NUMBERS; do
    pr_json=$(gh api "repos/${GH_REPO}/pulls/${num}" 2>/dev/null) || {
        echo "  ⚠ Could not fetch PR #${num}, skipping"
        continue
    }

    title=$(printf '%s' "$pr_json" | jq -r '.title')
    author=$(printf '%s' "$pr_json" | jq -r '.user.login // "unknown"')
    labels=$(printf '%s' "$pr_json" | jq -r '.labels[].name')

    matched=""
    for entry in "${CATEGORIES[@]}"; do
        label="${entry%%|*}"
        if printf '%s\n' "$labels" | grep -qx "$label"; then
            matched="$label"
            break
        fi
    done

    line="* ${title} by @${author} in https://github.com/${GH_REPO}/pull/${num}"
    if [[ -n "$matched" ]]; then
        printf '%s\n' "$line" >> "${PR_DATA_DIR}/${matched}"
    else
        printf '%s\n' "$line" >> "$OTHER_FILE"
        echo "  ⚠ PR #${num} has no recognized category label — bucketed under 'Other Changes'"
    fi

    printf '%s\t%s\n' "$author" "$num" >> "$AUTHORS_FILE"
done

# Detect new contributors: authors with zero merged PRs in this repo before PREV_TAG.
PREV_TAG_DATE=$(git log -1 --format=%cI "$PREV_TAG")
NEW_CONTRIBUTORS_FILE="${PR_DATA_DIR}/new-contributors"
: > "$NEW_CONTRIBUTORS_FILE"

UNIQUE_AUTHORS=$(awk -F'\t' '{print $1}' "$AUTHORS_FILE" | sort -u)
for author in $UNIQUE_AUTHORS; do
    [[ "$author" == "unknown" ]] && continue
    prior_count=$(gh api -X GET search/issues \
        -f q="repo:${GH_REPO} is:pr is:merged author:${author} merged:<${PREV_TAG_DATE}" \
        --jq '.total_count' 2>/dev/null) || prior_count="?"

    if [[ "$prior_count" == "0" ]]; then
        first_pr=$(awk -F'\t' -v a="$author" '$1==a {print $2; exit}' "$AUTHORS_FILE")
        printf '* @%s made their first contribution in https://github.com/%s/pull/%s\n' \
            "$author" "$GH_REPO" "$first_pr" >> "$NEW_CONTRIBUTORS_FILE"
    fi
done

# Emit GitHub Release body (with "What's Changed" framing + Full Changelog footer).
{
    echo "## What's Changed"
    for entry in "${CATEGORIES[@]}"; do
        label="${entry%%|*}"
        section_title="${entry#*|}"
        cat_file="${PR_DATA_DIR}/${label}"
        if [[ -s "$cat_file" ]]; then
            echo "### ${section_title}"
            cat "$cat_file"
        fi
    done
    if [[ -s "$OTHER_FILE" ]]; then
        echo "### Other Changes"
        cat "$OTHER_FILE"
    fi
    if [[ -s "$NEW_CONTRIBUTORS_FILE" ]]; then
        echo ""
        echo "## New Contributors"
        cat "$NEW_CONTRIBUTORS_FILE"
    fi
    echo ""
    echo "**Full Changelog**: https://github.com/${GH_REPO}/compare/${PREV_TAG}...${TAG}"
} > "$NOTES_FILE"

# Emit CHANGELOG.md section (no "What's Changed" header, no Full Changelog footer
# — the version heading and the project's link to GitHub Releases serve those roles).
TODAY=$(date +%Y-%m-%d)
{
    echo "## [${VERSION}] - ${TODAY}"
    echo ""
    for entry in "${CATEGORIES[@]}"; do
        label="${entry%%|*}"
        section_title="${entry#*|}"
        cat_file="${PR_DATA_DIR}/${label}"
        if [[ -s "$cat_file" ]]; then
            echo "### ${section_title}"
            cat "$cat_file"
        fi
    done
    if [[ -s "$OTHER_FILE" ]]; then
        echo "### Other Changes"
        cat "$OTHER_FILE"
    fi
    if [[ -s "$NEW_CONTRIBUTORS_FILE" ]]; then
        echo ""
        echo "## New Contributors"
        cat "$NEW_CONTRIBUTORS_FILE"
    fi
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

# Ensure split repos exist for any newly-added packages BEFORE pushing main or
# the tag. The split workflow fires on both pushes and will fail loudly if a
# package's split repo doesn't exist yet (this is what bit us during 0.5.0).
# The script is idempotent and uses a single batched API call, so a no-op run
# completes in ~1s.
echo "Verifying split repos exist for all packages..."
"${SCRIPT_DIR}/create-split-repos.sh"

echo "Pushing main branch..."
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
