#!/usr/bin/env bash
set -euo pipefail

REPO="marko-php/marko"

echo "Setting up GitHub labels for ${REPO}..."

gh label create "refactor" \
    --repo "${REPO}" \
    --color "d4c5f9" \
    --description "Code refactoring with no behavior change" \
    --force

echo "  Created: refactor"

gh label create "testing" \
    --repo "${REPO}" \
    --color "bfd4f2" \
    --description "Test additions or improvements" \
    --force

echo "  Created: testing"

gh label create "ci" \
    --repo "${REPO}" \
    --color "f9d0c4" \
    --description "CI/CD pipeline and automation changes" \
    --force

echo "  Created: ci"

gh label create "maintenance" \
    --repo "${REPO}" \
    --color "c2e0c6" \
    --description "Dependency updates and housekeeping" \
    --force

echo "  Created: maintenance"

gh label create "breaking" \
    --repo "${REPO}" \
    --color "e11d48" \
    --description "Introduces a breaking change" \
    --force

echo "  Created: breaking"

echo ""
echo "Done. All labels created (or updated) on ${REPO}."
