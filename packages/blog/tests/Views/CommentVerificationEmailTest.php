<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Views\CommentVerificationEmail;

use Marko\Blog\Entity\Comment;
use Marko\Blog\Entity\Post;
use Marko\Blog\Enum\CommentStatus;
use Marko\Blog\Enum\PostStatus;

describe('Comment Verification Email Template', function (): void {
    it('renders email subject with post title', function (): void {
        $view = \createBlogTestView();

        $post = createVerificationEmailPost(1, 'How to Build APIs', 'how-to-build-apis');
        $comment = createVerificationEmailComment($post, 'John Doe', 'john@example.com');

        $html = $view->renderToString('blog::email/comment-verification/html', [
            'comment' => $comment,
            'post' => $post,
            'verificationUrl' => 'https://example.com/verify?token=abc123',
            'expiresInDays' => 7,
            'siteName' => 'My Blog',
        ]);

        expect($html)->toContain('How to Build APIs');
    });

    it('includes commenter name in greeting', function (): void {
        $view = \createBlogTestView();

        $post = createVerificationEmailPost(1, 'Test Post', 'test-post');
        $comment = createVerificationEmailComment($post, 'Jane Smith', 'jane@example.com');

        $html = $view->renderToString('blog::email/comment-verification/html', [
            'comment' => $comment,
            'post' => $post,
            'verificationUrl' => 'https://example.com/verify?token=abc123',
            'expiresInDays' => 7,
            'siteName' => 'My Blog',
        ]);

        expect($html)->toMatch('/Hello,?\s+Jane Smith/i');
    });

    it('includes post title they commented on', function (): void {
        $view = \createBlogTestView();

        $post = createVerificationEmailPost(1, 'Advanced PHP Patterns', 'advanced-php-patterns');
        $comment = createVerificationEmailComment($post, 'Bob Wilson', 'bob@example.com');

        $html = $view->renderToString('blog::email/comment-verification/html', [
            'comment' => $comment,
            'post' => $post,
            'verificationUrl' => 'https://example.com/verify?token=abc123',
            'expiresInDays' => 7,
            'siteName' => 'My Blog',
        ]);

        expect($html)->toMatch('/commented\s+on.*Advanced PHP Patterns/is');
    });

    it('includes clickable verification link', function (): void {
        $view = \createBlogTestView();

        $post = createVerificationEmailPost(1, 'Test Post', 'test-post');
        $comment = createVerificationEmailComment($post, 'John Doe', 'john@example.com');
        $verificationUrl = 'https://example.com/blog/verify?token=unique123';

        $html = $view->renderToString('blog::email/comment-verification/html', [
            'comment' => $comment,
            'post' => $post,
            'verificationUrl' => $verificationUrl,
            'expiresInDays' => 7,
            'siteName' => 'My Blog',
        ]);

        expect($html)->toMatch('/<a[^>]*href\s*=\s*["\']' . preg_quote($verificationUrl, '/') . '["\']/i');
    });

    it('includes link expiration notice', function (): void {
        $view = \createBlogTestView();

        $post = createVerificationEmailPost(1, 'Test Post', 'test-post');
        $comment = createVerificationEmailComment($post, 'John Doe', 'john@example.com');

        $html = $view->renderToString('blog::email/comment-verification/html', [
            'comment' => $comment,
            'post' => $post,
            'verificationUrl' => 'https://example.com/verify?token=abc123',
            'expiresInDays' => 7,
            'siteName' => 'My Blog',
        ]);

        expect($html)->toMatch('/expires?\s+(in\s+)?7\s+days?/i');
    });

    it('includes plain text alternative', function (): void {
        $view = \createBlogTestView();

        $post = createVerificationEmailPost(1, 'Test Post', 'test-post');
        $comment = createVerificationEmailComment($post, 'John Doe', 'john@example.com');
        $verificationUrl = 'https://example.com/verify?token=abc123';

        $text = $view->renderToString('blog::email/comment-verification/text', [
            'comment' => $comment,
            'post' => $post,
            'verificationUrl' => $verificationUrl,
            'expiresInDays' => 7,
            'siteName' => 'My Blog',
        ]);

        expect($text)->not->toContain('<html')
            ->and($text)->not->toContain('<body')
            ->and($text)->toContain('John Doe')
            ->and($text)->toContain('Test Post')
            ->and($text)->toContain($verificationUrl)
            ->and($text)->toContain('7 days');
    });

    it('has professional formatting', function (): void {
        $view = \createBlogTestView();

        $post = createVerificationEmailPost(1, 'Test Post', 'test-post');
        $comment = createVerificationEmailComment($post, 'John Doe', 'john@example.com');

        $html = $view->renderToString('blog::email/comment-verification/html', [
            'comment' => $comment,
            'post' => $post,
            'verificationUrl' => 'https://example.com/verify?token=abc123',
            'expiresInDays' => 7,
            'siteName' => 'My Blog',
        ]);

        expect($html)->toContain('<!DOCTYPE html')
            ->and($html)->toContain('<html')
            ->and($html)->toContain('<head')
            ->and($html)->toContain('<body')
            ->and($html)->toContain('<style')
            ->and($html)->toContain('My Blog');
    });

    it('is mobile-responsive', function (): void {
        $view = \createBlogTestView();

        $post = createVerificationEmailPost(1, 'Test Post', 'test-post');
        $comment = createVerificationEmailComment($post, 'John Doe', 'john@example.com');

        $html = $view->renderToString('blog::email/comment-verification/html', [
            'comment' => $comment,
            'post' => $post,
            'verificationUrl' => 'https://example.com/verify?token=abc123',
            'expiresInDays' => 7,
            'siteName' => 'My Blog',
        ]);

        expect($html)->toMatch('/<meta[^>]*name\s*=\s*["\']viewport["\']/i')
            ->and($html)->toMatch('/@media[^{]*\(/i');
    });
});

function createVerificationEmailPost(
    int $id,
    string $title,
    string $slug,
): Post {
    $post = new Post(
        title: $title,
        content: "Content for $title",
        authorId: 1,
        slug: $slug,
    );
    $post->id = $id;
    $post->status = PostStatus::Published;
    $post->publishedAt = '2025-01-15 10:00:00';

    return $post;
}

function createVerificationEmailComment(
    Post $post,
    string $name,
    string $email,
): Comment {
    $comment = new Comment();
    $comment->id = 1;
    $comment->postId = $post->id ?? 0;
    $comment->name = $name;
    $comment->email = $email;
    $comment->content = 'This is a test comment.';
    $comment->status = CommentStatus::Pending;
    $comment->setPost($post);

    return $comment;
}
