# Task 001: Fix getting-started docs pages

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Update the two getting-started docs pages to match the real `Application::boot()` / `handleRequest()` API. The first-application page references `composer create-project marko/skeleton` which will exist after task 002. The project-structure page had a hallucinated API that now actually exists — verify it matches.

## Context
- Related files: `docs/src/content/docs/getting-started/first-application.md`, `docs/src/content/docs/getting-started/project-structure.md`
- The real bootstrap API is now: `Application::boot(dirname(__DIR__))` + `$app->handleRequest()`
- The `first-application.md` references `marko/skeleton` — keep this reference since task 002 creates it
- These are Astro markdown files with frontmatter

## Requirements (Test Descriptions)
- [ ] `it updates project-structure.md index.php example to match real Application::boot() API`
- [ ] `it updates first-application.md to use correct bootstrap pattern`
- [ ] `it preserves existing frontmatter and structure in both files`
- [ ] `it references Application::boot() and handleRequest() correctly`
- [ ] `it fixes controller example imports to use Marko\Routing\Http\Response instead of Marko\Http\Response`

## Acceptance Criteria
- Both docs pages show the real `Application::boot(dirname(__DIR__))` + `$app->handleRequest()` API
- `first-application.md` still references `marko/skeleton` for `create-project`
- No broken markdown or frontmatter
- Code examples are accurate and complete

## Implementation Notes
For `project-structure.md`, the current `public/index.php` section (lines 105-120) already shows `Application::boot(dirname(__DIR__))` and `$app->handleRequest()` — this was "hallucinated" before but now matches reality. **Verify it matches exactly** and fix if needed. Note: line 114 uses `require` (not `require_once`) -- this is correct and consistent with Composer conventions.

For `first-application.md`, the tutorial flow should work with the skeleton project. Keep `composer create-project marko/skeleton hello-marko` at the start. The tutorial doesn't show `public/index.php` directly since the skeleton provides it -- it just does `marko up` at step 4.

**Important -- incorrect imports in controller examples**: The `first-application.md` controller examples use `Marko\Http\Response`, `Marko\Http\ResponseInterface`, and `Marko\Http\JsonResponse`. These are all wrong:
- The actual class is `Marko\Routing\Http\Response` (namespace `Marko\Routing\Http`, not `Marko\Http`)
- There is no `ResponseInterface` in the codebase
- There is no `JsonResponse` class in the codebase

Fix the controller examples to use the correct `Marko\Routing\Http\Response` import. For the JSON endpoint (step 5), rewrite using `Response` with a JSON body and `Content-Type: application/json` header. The return type should be `Response` (not `ResponseInterface`). Example:
```php
return new Response(
    body: json_encode(['message' => "Hello, {$name}!", 'framework' => 'Marko']),
    headers: ['Content-Type' => 'application/json'],
);
```
