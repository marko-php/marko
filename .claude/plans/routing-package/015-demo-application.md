# Task 015: Demo Application with Blog Routes

**Status**: pending
**Depends on**: 014
**Retry count**: 0

## Description
Update the demo application and marko/blog package to use the routing system. Create minimal blog controllers that demonstrate routing works: route matching, parameter extraction, and Preference override. No fake data or pseudo-functionality - just prove the routing system functions correctly.

## Context
- Update: `demo/public/index.php` to use Router
- Update: `packages/blog/` with a simple controller
- Create: `demo/app/blog/` to demonstrate Preference override
- Without database, controllers return simple responses confirming the route matched
- Middleware excluded until there's a real use case for it

## Requirements (Test Descriptions)
- [ ] `demo index.php creates Request from globals`
- [ ] `demo index.php routes request through Router`
- [ ] `demo index.php sends Response to client`
- [ ] `demo index.php returns 404 for unmatched routes`
- [ ] `marko/blog has PostController with GET /blog route`
- [ ] `marko/blog has PostController with GET /blog/{slug} route`
- [ ] `PostController index returns response confirming route matched`
- [ ] `PostController show returns response including the slug parameter`
- [ ] `demo app/blog overrides PostController via Preference`
- [ ] `app PostController modifies show method response`
- [ ] `app PostController uses DisableRoute on a method to demonstrate route removal`

## Acceptance Criteria
- Demo app handles real HTTP requests
- Routes match and return responses
- Parameter extraction works (slug appears in response)
- Preference override works (app controller replaces vendor)
- DisableRoute works (route returns 404 after disable)
- No fake data or pseudo-functionality

## Files to Create/Update
```
demo/public/index.php                    # Update to use Router
packages/blog/src/
  Controllers/
    PostController.php                   # GET /blog, GET /blog/{slug}
demo/app/blog/
  composer.json                          # App module definition
  module.php                             # Sequence after marko/blog
  src/
    Controllers/
      PostController.php                 # Preference override
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
