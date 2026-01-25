# Task 030: Comment Verify Controller

**Status**: completed
**Depends on**: 014
**Retry count**: 0

## Description
Create controller for comment email verification. Handles the verification link clicked from email, verifies the comment, sets browser cookie, and redirects to the post with success message.

## Context
- Related files: `packages/blog/src/Controllers/CommentController.php`
- Patterns to follow: GET endpoint that performs action and redirects
- Route: GET /blog/comment/verify/{token} (prefix configurable via BlogConfig)
- Uses marko/session for flash messages

## Requirements (Test Descriptions)
- [x] `it verifies comment at GET /blog/comment/verify/{token}`
- [x] `it returns error page when token not found`
- [x] `it returns error page when token is expired`
- [x] `it marks comment as verified on valid token`
- [x] `it sets browser cookie with verification token`
- [x] `it uses configured cookie name from BlogConfig`
- [x] `it uses configured cookie expiry days from BlogConfig`
- [x] `it sets cookie as HttpOnly and Secure`
- [x] `it redirects to post page after verification`
- [x] `it sets success flash message on redirect`
- [x] `it dispatches CommentVerified event`
- [x] `it deletes used email verification token`

## Flash Message Flow

```
User clicks verification link
  → GET /blog/comment/verify/{token}
  → Controller verifies token via CommentVerificationServiceInterface
  → Controller sets flash message via SessionInterface::flash()
  → Controller sets browser cookie
  → Controller redirects to post page
  → Post page renders flash message from session
```

## Controller Implementation

```php
#[RoutePrefix(configKey: 'blog.route_prefix', default: '/blog')]
class CommentController
{
    public function __construct(
        private readonly CommentVerificationServiceInterface $verificationService,
        private readonly VerificationTokenRepositoryInterface $tokenRepository,
        private readonly SessionInterface $session,
        private readonly EventDispatcherInterface $events,
    ) {}

    #[Get('/comment/verify/{token}')]
    public function verify(string $token): Response
    {
        // Find the token to get associated comment
        $verificationToken = $this->tokenRepository->findByToken($token);

        if ($verificationToken === null) {
            return $this->renderError('Invalid verification link.');
        }

        if ($verificationToken->isExpired()) {
            return $this->renderError('This verification link has expired. Please submit your comment again.');
        }

        // Verify the comment and get browser token
        $browserToken = $this->verificationService->verifyByToken($token);
        $comment = $verificationToken->getComment();

        // Dispatch event
        $this->events->dispatch(new CommentVerified(
            comment: $comment,
            verificationMethod: 'email',
        ));

        // Set flash message
        $this->session->flash('success', 'Your comment has been verified and is now visible.');

        // Create cookie
        $cookie = new Cookie(
            name: $this->verificationService->getCookieName(),
            value: $browserToken,
            expires: time() + ($this->verificationService->getCookieLifetimeDays() * 86400),
            httpOnly: true,
            secure: true,
            sameSite: 'Lax',
        );

        // Redirect to post with cookie
        $postUrl = $this->generateUrl('blog.post.show', ['slug' => $comment->getPost()->getSlug()]);

        return $this->redirect($postUrl . '#comment-' . $comment->getId())
            ->withCookie($cookie);
    }

    private function renderError(string $message): Response
    {
        return $this->view->render('blog::comment/verify-error', [
            'message' => $message,
        ]);
    }
}
```

## Acceptance Criteria
- All requirements have passing tests
- Route GET /{prefix}/comment/verify/{token} completes verification
- Uses interfaces for all dependencies (injected via DI)
- Controllers swappable via Preferences for customization
- Sets HttpOnly, Secure cookie with SameSite=Lax
- Uses BlogConfig for cookie name and lifetime
- Redirects to post page with anchor to comment
- Flash message stored in session for display on redirect
- Error cases show user-friendly error page
- Code follows Marko standards

## Implementation Notes

Implementation completed with the following key decisions:

1. **Created CookieJarInterface** (`packages/blog/src/Contracts/CookieJarInterface.php`) - A simple interface for cookie handling in the blog package, allowing for framework-agnostic cookie management.

2. **Created VerificationResult DTO** (`packages/blog/src/Dto/VerificationResult.php`) - A readonly data transfer object that returns browserToken, postSlug, and commentId from verifyByToken(). This provides all the information needed for redirect and cookie setting in a clean, typed structure.

3. **Modified CommentVerificationServiceInterface** - Changed `verifyByToken()` return type from `string` to `VerificationResult` to provide complete verification context including post slug for redirect.

4. **Modified CommentVerificationService** - Added `PostRepositoryInterface` dependency to fetch the associated post and retrieve its slug for the VerificationResult.

5. **Controller Implementation** (`packages/blog/src/Controllers/CommentVerifyController.php`):
   - Route: GET /blog/comment/verify/{token}
   - On valid token: sets HttpOnly/Secure/SameSite=Lax cookie, flash success message, redirects to post page with #comment-{id} anchor
   - On invalid/expired token: returns 400 error page via view rendering
   - Uses constructor property promotion for all dependencies

6. **Test Coverage** - All 12 tests pass with comprehensive mocking using anonymous classes implementing required interfaces. Helper classes (MockCookieJar, MockSession, MockFlashBag, CallCapture) provide clean test infrastructure.
