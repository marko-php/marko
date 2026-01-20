# Task 015: Demo Application with Blog Routes

**Status**: completed
**Depends on**: 014
**Retry count**: 0

## Description
Update the demo application and marko/blog package to use the routing system. The blog package gets real controllers with routes. The demo app wires everything together. No pseudo-functionality in demo/app/ - only add overrides when there's a real reason to customize.

## Context
- Update: `demo/public/index.php` to use Router
- Update: `packages/blog/` with a simple controller
- Create: `demo/app/blog/` module structure (empty until there's real customization needed)
- Without database, controllers return simple responses confirming the route matched
- Middleware excluded until there's a real use case for it

## Requirements (Test Descriptions)
- [x] `demo index.php creates Request from globals`
- [x] `demo index.php routes request through Router`
- [x] `demo index.php sends Response to client`
- [x] `demo index.php returns 404 for unmatched routes`
- [x] `marko/blog has PostController with GET /blog route`
- [x] `marko/blog has PostController with GET /blog/{slug} route`
- [x] `PostController index returns response confirming route matched`
- [x] `PostController show returns response including the slug parameter`
- [x] `demo/app/blog module structure exists for future customization`

## Acceptance Criteria
- Demo app handles real HTTP requests
- Routes match and return responses
- Parameter extraction works (slug appears in response)
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
  src/.gitkeep                           # Empty until real customization needed
```

## Implementation Notes
**Post-implementation fix:** The original plan included requirements for demo/app/blog to override PostController "to demonstrate Preference override" and "to demonstrate route removal." This was pseudo-functionality - overriding code just to show features work, not because there was a real reason to customize. The demo/app/blog/PostController was removed. Feature verification belongs in tests (which use fixtures), not in demo code. The module structure remains for future legitimate customizations.
