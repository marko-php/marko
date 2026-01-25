# Task 050: Verification Email Template

**Status**: pending
**Depends on**: 014
**Retry count**: 0

## Description
Create the email template for comment verification. The email contains a verification link that the commenter clicks to verify their comment.

## Context
- Related files: `packages/blog/resources/views/email/comment-verification.latte`
- Patterns to follow: Marko email templates via marko/mail
- Uses marko/view for rendering (works with any view engine)
- Template receives comment, post, and verification URL as variables

## Requirements (Test Descriptions)
- [ ] `it renders email subject with post title`
- [ ] `it includes commenter name in greeting`
- [ ] `it includes post title they commented on`
- [ ] `it includes clickable verification link`
- [ ] `it includes link expiration notice`
- [ ] `it includes plain text alternative`
- [ ] `it has professional formatting`
- [ ] `it is mobile-responsive`

## Template Variables

```php
// Passed to template by CommentVerificationService
$variables = [
    'comment' => $comment,           // CommentInterface
    'post' => $post,                 // PostInterface
    'verificationUrl' => $url,       // Full URL with token
    'expiresInDays' => 7,            // From BlogConfig
    'siteName' => 'My Blog',         // From app config
];
```

## Email Template Structure

### HTML Version (comment-verification.latte)

```latte
{layout 'email::layout'}

{block subject}Verify your comment on "{$post->getTitle()}"{/block}

{block content}
<h1>Hi {$comment->getAuthorName()},</h1>

<p>Thank you for commenting on <strong>"{$post->getTitle()}"</strong>.</p>

<p>To publish your comment, please verify your email address by clicking the button below:</p>

<p style="text-align: center; margin: 30px 0;">
    <a href="{$verificationUrl}" class="button">Verify My Comment</a>
</p>

<p>Or copy and paste this link into your browser:</p>
<p style="word-break: break-all; color: #666;">
    {$verificationUrl}
</p>

<p><strong>This link will expire in {$expiresInDays} days.</strong></p>

<p>If you didn't submit this comment, you can safely ignore this email.</p>

<p>
Thanks,<br>
{$siteName}
</p>
{/block}
```

### Plain Text Version (comment-verification.txt.latte)

```latte
Hi {$comment->getAuthorName()},

Thank you for commenting on "{$post->getTitle()}".

To publish your comment, please verify your email address by visiting:

{$verificationUrl}

This link will expire in {$expiresInDays} days.

If you didn't submit this comment, you can safely ignore this email.

Thanks,
{$siteName}
```

## Email Layout (Optional Base)

If marko/mail provides a base layout:

```latte
{* email::layout *}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{block subject}{/block}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; line-height: 1.6; color: #333; }
        .button { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }
        .button:hover { background: #0056b3; }
    </style>
</head>
<body style="padding: 20px;">
    {block content}{/block}
</body>
</html>
```

## Integration with CommentVerificationService

```php
// CommentVerificationService::sendVerificationEmail()
public function sendVerificationEmail(CommentInterface $comment): string
{
    $token = $this->generateToken();
    $post = $comment->getPost();

    // Store token in database
    $this->tokenRepository->create(
        token: $token,
        email: $comment->getAuthorEmail(),
        commentId: $comment->getId(),
        type: 'email',
        expiresAt: new DateTimeImmutable('+' . $this->config->getVerificationTokenExpiryDays() . ' days'),
    );

    // Generate verification URL
    $verificationUrl = $this->urlGenerator->generate(
        'blog.comment.verify',
        ['token' => $token],
        absolute: true,
    );

    // Send email
    $this->mailer->send(
        to: $comment->getAuthorEmail(),
        template: 'blog::email/comment-verification',
        variables: [
            'comment' => $comment,
            'post' => $post,
            'verificationUrl' => $verificationUrl,
            'expiresInDays' => $this->config->getVerificationTokenExpiryDays(),
            'siteName' => $this->appConfig->get('app.name', 'Blog'),
        ],
    );

    return $token;
}
```

## Acceptance Criteria
- All requirements have passing tests
- HTML email template renders correctly
- Plain text alternative provided
- Email is mobile-responsive
- Verification link is correct and clickable
- Expiration notice clearly displayed
- Template is view-engine agnostic (works with any driver)
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
