<?php

declare(strict_types=1);

use Marko\Blog\Entity\Comment;
describe('Comment Thread Component', function (): void {
    it('renders single comment with author name and content', function (): void {
        $view = createBlogTestView();

        $comment = createTestComment(
            id: 1,
            name: 'John Doe',
            content: 'This is a great post!',
        );

        $html = $view->renderToString('blog::comment/thread', [
            'comments' => [$comment],
            'maxDepth' => 5,
            'postId' => 1,
        ]);

        expect($html)->toContain('John Doe')
            ->and($html)->toContain('This is a great post!');
    });

    it('displays comment created date', function (): void {
        $view = createBlogTestView();

        $comment = createTestComment(
            id: 1,
            name: 'Jane Doe',
            content: 'Nice article!',
            createdAt: '2024-03-15 14:30:00',
        );

        $html = $view->renderToString('blog::comment/thread', [
            'comments' => [$comment],
            'maxDepth' => 5,
            'postId' => 1,
        ]);

        // Check that the date is displayed (format may vary, but should contain key date parts)
        expect($html)->toMatch('/March\s+15,?\s+2024|2024-03-15|Mar\s+15/i');
    });

    it('renders nested replies with indentation', function (): void {
        $view = createBlogTestView();

        $childComment = createTestComment(
            id: 2,
            name: 'Reply Author',
            content: 'This is a reply!',
            parentId: 1,
        );

        $parentComment = createTestComment(
            id: 1,
            name: 'Parent Author',
            content: 'This is the parent comment.',
            children: [$childComment],
        );

        $html = $view->renderToString('blog::comment/thread', [
            'comments' => [$parentComment],
            'maxDepth' => 5,
            'postId' => 1,
        ]);

        // Should contain both comments
        expect($html)->toContain('Parent Author')
            ->and($html)->toContain('Reply Author')
            // Nested comments should have depth-based CSS class for indentation
            ->and($html)->toMatch('/class\s*=\s*["\'][^"\']*comment-depth-1[^"\']*["\']/');
    });

    it('respects max depth configuration', function (): void {
        $view = createBlogTestView();

        // Create deeply nested comments: level 0 -> level 1 -> level 2
        $level2Comment = createTestComment(
            id: 3,
            name: 'Level 2 Author',
            content: 'Should not be rendered',
            parentId: 2,
        );

        $level1Comment = createTestComment(
            id: 2,
            name: 'Level 1 Author',
            content: 'First level reply',
            parentId: 1,
            children: [$level2Comment],
        );

        $rootComment = createTestComment(
            id: 1,
            name: 'Root Author',
            content: 'Root comment',
            children: [$level1Comment],
        );

        // With maxDepth=2, only depth 0 and 1 should be rendered
        $html = $view->renderToString('blog::comment/thread', [
            'comments' => [$rootComment],
            'maxDepth' => 2,
            'postId' => 1,
        ]);

        expect($html)->toContain('Root Author')
            ->and($html)->toContain('Level 1 Author')
            // Level 2 should not be rendered when maxDepth is 2
            ->and($html)->not->toContain('Level 2 Author');
    });

    it('shows reply link for comments under max depth', function (): void {
        $view = createBlogTestView();

        $comment = createTestComment(
            id: 1,
            name: 'Test Author',
            content: 'A comment that can be replied to.',
        );

        $html = $view->renderToString('blog::comment/thread', [
            'comments' => [$comment],
            'maxDepth' => 3,
            'postId' => 42,
        ]);

        // Reply link should be present for comments under max depth
        expect($html)->toMatch('/<a[^>]*class\s*=\s*["\'][^"\']*comment-reply-link[^"\']*["\']/');
    });

    it('hides reply link at max depth', function (): void {
        $view = createBlogTestView();

        $childComment = createTestComment(
            id: 2,
            name: 'Max Depth Author',
            content: 'At max depth, cannot reply.',
            parentId: 1,
        );

        $parentComment = createTestComment(
            id: 1,
            name: 'Parent Author',
            content: 'Parent comment.',
            children: [$childComment],
        );

        // maxDepth=2 means depth 0 and 1 are allowed
        // Child at depth 1 should NOT have a reply link (would be depth 2)
        $html = $view->renderToString('blog::comment/thread', [
            'comments' => [$parentComment],
            'maxDepth' => 2,
            'postId' => 1,
        ]);

        // Parse to find the child comment specifically
        // The child comment should not have a reply link
        // Count reply links - should only be 1 (for parent at depth 0)
        preg_match_all('/class\s*=\s*["\'][^"\']*comment-reply-link[^"\']*["\']/', $html, $matches);
        expect(count($matches[0]))->toBe(1);
    });

    it('shows message when no comments', function (): void {
        $view = createBlogTestView();

        $html = $view->renderToString('blog::comment/thread', [
            'comments' => [],
            'maxDepth' => 5,
            'postId' => 1,
        ]);

        expect($html)->toMatch('/no\s+comments|be\s+the\s+first/i');
    });

    it('has semantic HTML structure', function (): void {
        $view = createBlogTestView();

        $comment = createTestComment(
            id: 1,
            name: 'Test Author',
            content: 'Test content.',
        );

        $html = $view->renderToString('blog::comment/thread', [
            'comments' => [$comment],
            'maxDepth' => 5,
            'postId' => 1,
        ]);

        // Should use semantic HTML elements
        expect($html)->toContain('<article')
            ->and($html)->toContain('<header')
            ->and($html)->toContain('<time')
            ->and($html)->toContain('<footer');
    });

    it('includes proper ARIA labels for accessibility', function (): void {
        $view = createBlogTestView();

        $comment = createTestComment(
            id: 1,
            name: 'Test Author',
            content: 'Test content.',
        );

        $html = $view->renderToString('blog::comment/thread', [
            'comments' => [$comment],
            'maxDepth' => 5,
            'postId' => 1,
        ]);

        // Should have aria-label on articles for comment identification
        expect($html)->toMatch('/aria-label\s*=\s*["\'].*[Cc]omment.*["\']/');
    });

    it('displays comment count', function (): void {
        $view = createBlogTestView();

        $comment1 = createTestComment(
            id: 1,
            name: 'Author 1',
            content: 'First comment.',
        );

        $comment2 = createTestComment(
            id: 2,
            name: 'Author 2',
            content: 'Second comment.',
        );

        $comment3 = createTestComment(
            id: 3,
            name: 'Author 3',
            content: 'Third comment.',
        );

        $html = $view->renderToString('blog::comment/thread', [
            'comments' => [$comment1, $comment2, $comment3],
            'maxDepth' => 5,
            'postId' => 1,
            'commentCount' => 3,
        ]);

        // Should display comment count
        expect($html)->toMatch('/3\s+[Cc]omments/');
    });
});

function createTestComment(
    int $id,
    string $name,
    string $content,
    ?int $parentId = null,
    ?string $createdAt = null,
    array $children = [],
): Comment {
    $comment = new Comment();
    $comment->id = $id;
    $comment->postId = 1;
    $comment->name = $name;
    $comment->email = 'test@example.com';
    $comment->content = $content;
    $comment->parentId = $parentId;
    $comment->createdAt = $createdAt ?? '2024-01-15 10:30:00';
    $comment->setChildren($children);

    return $comment;
}
