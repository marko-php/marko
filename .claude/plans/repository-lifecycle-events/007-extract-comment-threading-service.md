# Task 007: Extract CommentThreadingService from CommentRepository

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Extract the comment threading/tree-building logic from CommentRepository into a dedicated CommentThreadingService. This removes the misplaced BlogConfigInterface dependency from the repository. The threading logic is presentation/business logic, not data persistence.

## Context
- Related files:
  - `packages/blog/src/Repositories/CommentRepository.php` — source of threading logic
  - `packages/blog/src/Repositories/CommentRepositoryInterface.php` — remove `getThreadedCommentsForPost()`
  - `packages/blog/src/Controllers/PostController.php:104` — calls `getThreadedCommentsForPost()`
  - `packages/blog/src/Controllers/CommentController.php:187` — calls `calculateDepth()`
  - `packages/blog/src/Config/BlogConfigInterface.php` — provides `getCommentMaxDepth()`
  - `packages/blog/tests/Repositories/CommentRepositoryTest.php` — threading tests move
  - Mock files that implement `getThreadedCommentsForPost()`:
    - `packages/blog/tests/Mocks/MockCommentRepository.php:61`
    - `packages/blog/tests/Services/CommentVerificationServiceTest.php:589`
    - `packages/blog/tests/Controllers/PostControllerTest.php:1600`
    - `packages/blog/tests/Controllers/CommentControllerTest.php:906`
    - `packages/blog/tests/Unit/Admin/Controllers/CommentAdminControllerTest.php:357`
    - `packages/blog/tests/Unit/Admin/Api/BlogApiResponseFormatTest.php:392`
    - `packages/blog/tests/Unit/Admin/Api/CommentApiControllerTest.php:216`

## Requirements (Test Descriptions)

### CommentThreadingServiceInterface
- [ ] `it defines getThreadedComments method accepting post id`
- [ ] `it defines calculateDepth method accepting comment id`

### CommentThreadingService
- [ ] `it returns empty array when no comments exist for post`
- [ ] `it returns root comments with no children as flat list`
- [ ] `it nests child comments under their parent`
- [ ] `it respects max depth configuration from BlogConfig`
- [ ] `it flattens comments exceeding max depth to the max depth level`
- [ ] `it calculates depth 0 for root comments`
- [ ] `it calculates depth 1 for direct replies`
- [ ] `it calculates depth correctly for deeply nested comments`
- [ ] `it returns 0 when comment does not exist`

### CommentRepositoryInterface Changes
- [ ] `it does not define getThreadedCommentsForPost method`
- [ ] `it does not define calculateDepth method`

### Caller Updates
- [ ] `it injects CommentThreadingServiceInterface in PostController for getThreadedComments`
- [ ] `it injects CommentThreadingServiceInterface in CommentController for calculateDepth`

## Implementation Notes

### New Files
- `packages/blog/src/Services/CommentThreadingServiceInterface.php`
- `packages/blog/src/Services/CommentThreadingService.php`

### CommentThreadingService
```php
class CommentThreadingService implements CommentThreadingServiceInterface {
    public function __construct(
        private readonly CommentRepositoryInterface $commentRepository,
        private readonly BlogConfigInterface $blogConfig,
    ) {}

    public function getThreadedComments(int $postId): array {
        $comments = $this->commentRepository->findVerifiedForPost($postId);
        return $this->buildTree($comments);
    }
    // Move buildTree(), calculateDepthFromMap(), findEffectiveParent() here

    public function calculateDepth(int $commentId): int {
        // Move from CommentRepository — walks parent chain
        // Note: currently does N+1 queries; acceptable for now, optimize later if needed
    }
}
```

### Remove from CommentRepository
- `getThreadedCommentsForPost()`
- `buildTree()`
- `calculateDepthFromMap()`
- `findEffectiveParent()`
- `calculateDepth()`
- `BlogConfigInterface` constructor dependency

### Remove from CommentRepositoryInterface
- `getThreadedCommentsForPost()` method
- `calculateDepth()` method

### Update PostController
- Inject `CommentThreadingServiceInterface` instead of calling `$this->commentRepository->getThreadedCommentsForPost()`

### Update all mocks
- Remove `getThreadedCommentsForPost()` from all mock CommentRepository implementations in tests
- Add mock CommentThreadingService where needed

## Size Warning
This task touches 10+ files including deeply embedded anonymous class mocks. Take care to update ALL mocks listed in the Context section. Run the full blog test suite after changes.

## Acceptance Criteria
- All requirements have passing tests
- CommentRepository has no BlogConfigInterface dependency
- CommentRepositoryInterface has no getThreadedCommentsForPost()
- All existing threading tests pass (moved to CommentThreadingService tests)
- PostController uses CommentThreadingServiceInterface
- All mock implementations updated
- `packages/blog/README.md` updated to remove `getThreadedCommentsForPost()` from API reference and document CommentThreadingServiceInterface
