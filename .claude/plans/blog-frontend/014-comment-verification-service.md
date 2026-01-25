# Task 014: Comment Verification Service

**Status**: pending
**Depends on**: 012, 013
**Retry count**: 0

## Description
Create the service handling comment email verification workflow: generating tokens, sending verification emails, processing verification links, and managing browser cookies for auto-approval.

## Context
- Related files: `packages/blog/src/Services/CommentVerificationService.php`
- Patterns to follow: Interface/implementation split
- Uses marko/mail for sending emails, BlogConfig for settings
- Uses marko/session for flash messages after verification

## Requirements (Test Descriptions)
- [ ] `it generates unique verification token for comment`
- [ ] `it sends verification email with link to commenter`
- [ ] `it verifies comment when valid token provided`
- [ ] `it rejects expired verification tokens`
- [ ] `it rejects invalid verification tokens`
- [ ] `it creates browser token after successful verification`
- [ ] `it checks if browser token is valid for email`
- [ ] `it auto-approves comment when valid browser token exists`
- [ ] `it returns verification token cookie value after verification`
- [ ] `it uses configured token expiry days`
- [ ] `it uses configured cookie name from BlogConfig`
- [ ] `it allows resending verification email for pending comment`
- [ ] `it invalidates old token when resending verification email`

## CommentVerificationServiceInterface

```php
interface CommentVerificationServiceInterface
{
    /**
     * Create a verification token and send email to commenter.
     *
     * @return string The generated token (for testing purposes)
     */
    public function sendVerificationEmail(CommentInterface $comment): string;

    /**
     * Verify a comment using the email verification token.
     *
     * @throws InvalidTokenException If token is invalid
     * @throws ExpiredTokenException If token has expired
     * @return string The browser token to set as cookie
     */
    public function verifyByToken(string $token): string;

    /**
     * Check if a browser token is valid for the given email.
     */
    public function isBrowserTokenValid(
        string $browserToken,
        string $email,
    ): bool;

    /**
     * Check if commenter should be auto-approved based on cookie.
     */
    public function shouldAutoApprove(
        string $email,
        ?string $browserToken,
    ): bool;

    /**
     * Get the cookie name for browser verification token.
     */
    public function getCookieName(): string;

    /**
     * Get the cookie lifetime in days.
     */
    public function getCookieLifetimeDays(): int;
}
```

## Flash Message Handling

After successful verification, the controller (Task 030) sets a flash message via `marko/session`:

```php
// CommentVerifyController
public function verify(string $token): Response
{
    $browserToken = $this->verificationService->verifyByToken($token);
    $comment = $this->getCommentFromToken($token);

    // Set success flash message via session
    $this->session->flash('success', 'Your comment has been verified and is now visible.');

    // Set browser cookie
    $cookie = new Cookie(
        name: $this->verificationService->getCookieName(),
        value: $browserToken,
        expires: time() + ($this->verificationService->getCookieLifetimeDays() * 86400),
        httpOnly: true,
        secure: true,
        sameSite: 'Lax',
    );

    // Redirect to post with flash message
    return $this->redirect($comment->getPost()->getUrl())
        ->withCookie($cookie);
}
```

The flash message is then displayed in the view template on the post page.

## Acceptance Criteria
- All requirements have passing tests
- CommentVerificationServiceInterface defined
- CommentVerificationService implements full workflow
- Uses BlogConfigInterface for cookie name and expiry settings
- Integrates with mail system for sending verification emails
- Cookie settings (name, lifetime) configurable via BlogConfig
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
