# Task 047: Module Bindings Configuration

**Status**: pending
**Depends on**: 001, 002, 003, 004, 005, 013, 014, 015, 016, 017, 018, 043
**Retry count**: 0

## Description
Create the module.php file with all interface-to-implementation bindings. This is the central wiring that connects all interfaces to their default implementations, enabling Preference swapping.

## Context
- Related files: `packages/blog/module.php`
- Patterns to follow: Marko module configuration pattern
- All services, repositories, and config use interfaces bound here

## Requirements (Test Descriptions)
- [ ] `it binds BlogConfigInterface to BlogConfig`
- [ ] `it binds SlugGeneratorInterface to SlugGenerator`
- [ ] `it binds AuthorRepositoryInterface to AuthorRepository`
- [ ] `it binds CategoryRepositoryInterface to CategoryRepository`
- [ ] `it binds TagRepositoryInterface to TagRepository`
- [ ] `it binds PostRepositoryInterface to PostRepository`
- [ ] `it binds CommentRepositoryInterface to CommentRepository`
- [ ] `it binds VerificationTokenRepositoryInterface to VerificationTokenRepository`
- [ ] `it binds CommentVerificationServiceInterface to CommentVerificationService`
- [ ] `it binds CommentRateLimiterInterface to CommentRateLimiter`
- [ ] `it binds HoneypotValidatorInterface to HoneypotValidator`
- [ ] `it binds PaginationServiceInterface to PaginationService`
- [ ] `it binds SearchServiceInterface to SearchService`
- [ ] `it binds SeoMetaServiceInterface to SeoMetaService`
- [ ] `it declares module dependencies on marko/core marko/routing marko/database marko/view marko/cache marko/mail marko/config marko/session`
- [ ] `it suggests marko/view-latte for default templates`
- [ ] `it suggests marko/csrf for CSRF protection on comment forms`
- [ ] `it does not require specific drivers allowing custom implementations`
- [ ] `it allows all bindings to be overridden via Preferences`

## Acceptance Criteria
- All requirements have passing tests
- module.php created with complete bindings array
- composer.json updated with all required dependencies (marko/config, marko/session, marko/cache, marko/mail)
- composer.json updated with suggested packages (marko/view-latte, marko/csrf)
- All interfaces resolvable via DI container
- Third parties can swap any implementation via Preferences
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
