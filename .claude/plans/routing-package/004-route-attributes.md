# Task 004: Route Attributes

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the HTTP method attributes (#[Get], #[Post], #[Put], #[Patch], #[Delete]) that define routes on controller methods. Each accepts a path and optional middleware array. These are the primary way developers define routes in Marko.

## Context
- Location: `packages/routing/src/Attributes/`
- Attributes target methods (Attribute::TARGET_METHOD)
- Path uses curly braces for parameters: `/posts/{id}`
- Middleware is an array of class names
- All route attributes share common structure (consider base class or trait)

## Requirements (Test Descriptions)
- [ ] `Get attribute accepts path parameter`
- [ ] `Get attribute accepts optional middleware array parameter`
- [ ] `Get attribute defaults middleware to empty array`
- [ ] `Post attribute accepts path and optional middleware`
- [ ] `Put attribute accepts path and optional middleware`
- [ ] `Patch attribute accepts path and optional middleware`
- [ ] `Delete attribute accepts path and optional middleware`
- [ ] `all route attributes target methods only`
- [ ] `route attributes are readonly`
- [ ] `route attributes expose method property matching HTTP method`

## Acceptance Criteria
- All requirements have passing tests
- Attributes are simple and declarative
- DRY implementation (shared logic for all HTTP methods)
- Attributes clearly document their parameters

## Files to Create
```
packages/routing/src/Attributes/
  Route.php           # Base class with path, middleware, method
  Get.php             # #[Get('/path')]
  Post.php            # #[Post('/path')]
  Put.php             # #[Put('/path')]
  Patch.php           # #[Patch('/path')]
  Delete.php          # #[Delete('/path')]
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
