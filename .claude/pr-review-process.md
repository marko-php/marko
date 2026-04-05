# PR Review Process

Step-by-step process for reviewing, updating, and merging external pull requests.

## 1. Fetch and understand the PR

```bash
# Get PR details, related issues, and diff
gh pr view <number> --json title,body,state,headRefName,baseRefName,author,commits
gh pr diff <number>

# Check the related issue if referenced
gh issue view <issue-number> --json title,body,state
```

Read the PR description and related issue. Understand what problem is being solved and whether the issue is valid/real.

## 2. Checkout the PR locally

```bash
# Ensure develop is up to date first
git checkout develop && git pull origin develop

# Checkout the PR branch
gh pr checkout <number>
```

## 3. Rebase onto develop

External PRs are often behind `develop`. Rebase before testing:

```bash
git rebase develop
```

If there are conflicts, resolve them. If the rebase is clean, proceed.

## 4. Run the test suite

```bash
./vendor/bin/pest --parallel
```

All tests must pass. If tests fail, determine whether failures are from the PR changes or pre-existing issues.

## 5. Review the code

Check each changed file against project standards:

- **`declare(strict_types=1)`** in every PHP file
- **Constructor property promotion** used consistently
- **No unnecessary imports** (self-namespace, unused)
- **No hardcoded paths or environment-specific values** — use `PHP_BINARY`, env vars, etc.
- **Tests exist** for new classes, bindings, and behavior
- **Module bindings** have corresponding tests in `ModuleBindingsTest.php`
- **No `final` classes** (blocks Preferences extensibility)
- **Type declarations** on all parameters, returns, properties
- **Compare with sibling packages** — if adding something to `database-mysql`, check how `database-pgsql` does it and ensure consistency

## 6. Make fixes directly on the PR

If the contributor enabled "Allow edits from maintainers" (`maintainerCanModify: true`), push fixes directly to their branch:

```bash
# Check if we can push
gh pr view <number> --json maintainerCanModify,headRepositoryOwner

# Add their fork as a remote (one-time)
git remote add <username> git@github.com:<username>/marko.git

# After making changes and committing
git push <username> <branch-name>

# If rebased (history changed), force push is needed
git push <username> <branch-name> --force-with-lease=<branch>:<original-commit-sha>
```

If `maintainerCanModify` is `false`, comment on the PR requesting changes instead.

Common fixes to make:
- Rebase onto current `develop`
- Add missing tests
- Fix lint/style issues
- Remove hardcoded paths or environment-specific values
- Remove unnecessary imports

## 7. Update the PR description

After adding commits, update the PR body to document what was changed:

```bash
gh api repos/marko-php/marko/pulls/<number> -X PATCH -f body="$(cat <<'EOF'
<updated body with new section for maintainer changes>
EOF
)"
```

Add a section like:

```markdown
### Additional changes (maintainer)
- Rebased onto current `develop`
- Added missing tests for ...
- Fixed lint issues in ...
```

## 8. Merge

Once everything passes and the review is complete, merge via GitHub. Prefer the merge commit strategy to preserve the contributor's commit history.

## Checklist

Before merging any PR:

- [ ] Issue is valid and the fix is correct
- [ ] Branch is rebased onto current `develop`
- [ ] All tests pass (`./vendor/bin/pest --parallel`)
- [ ] New code has corresponding tests
- [ ] No hardcoded paths or environment-specific values
- [ ] Code follows project standards (strict types, type declarations, no final classes)
- [ ] PR description accurately reflects all changes
- [ ] Clean up any accidental branches pushed to origin
