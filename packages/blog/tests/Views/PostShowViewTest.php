<?php

declare(strict_types=1);

use Marko\Blog\Entity\Author;
use Marko\Blog\Entity\Category;
use Marko\Blog\Entity\Comment;
use Marko\Blog\Entity\Post;
use Marko\Blog\Entity\Tag;
use Marko\Blog\Enum\PostStatus;
describe('Post Show View', function (): void {
    it('renders post title', function (): void {
        $view = createBlogTestView();

        $post = createPostShowTestPost(title: 'My Amazing Blog Post');

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
        ]);

        expect($html)->toMatch('/<h1[^>]*>.*My Amazing Blog Post.*<\/h1>/s');
    });

    it('renders full post content', function (): void {
        $view = createBlogTestView();

        $post = createPostShowTestPost(
            content: '<p>This is the full post content.</p><p>Second paragraph with more details.</p>',
        );

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
        ]);

        expect($html)->toContain('<p>This is the full post content.</p>')
            ->and($html)->toContain('<p>Second paragraph with more details.</p>');
    });

    it('displays author name with link to archive', function (): void {
        $view = createBlogTestView();

        $author = createPostShowTestAuthor(name: 'John Doe', slug: 'john-doe');
        $post = createPostShowTestPost();
        $post->setAuthor($author);

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
        ]);

        expect($html)->toContain('John Doe')
            ->and($html)->toMatch(
                '/<a[^>]*href\s*=\s*["\'][^"\']*\/blog\/author\/john-doe["\'][^>]*>.*John Doe.*<\/a>/s',
            );
    });

    it('displays author bio', function (): void {
        $view = createBlogTestView();

        $author = createPostShowTestAuthor(bio: 'A passionate developer and tech writer.');
        $post = createPostShowTestPost();
        $post->setAuthor($author);

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
        ]);

        expect($html)->toContain('A passionate developer and tech writer.');
    });

    it('displays published date', function (): void {
        $view = createBlogTestView();

        $post = createPostShowTestPost(publishedAt: '2025-03-20 14:30:00');

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
        ]);

        expect($html)->toMatch('/<time[^>]*datetime\s*=\s*["\']2025-03-20/i')
            ->and($html)->toMatch('/March\s+20,?\s+2025|Mar\s+20,?\s+2025|2025-03-20/');
    });

    it('displays last updated date when updated_at is after published_at', function (): void {
        $view = createBlogTestView();

        $post = createPostShowTestPost(
            publishedAt: '2025-03-20 14:30:00',
            updatedAt: '2025-04-15 10:00:00',
        );

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
        ]);

        expect($html)->toMatch('/updated/i')
            ->and($html)->toMatch('/<time[^>]*datetime\s*=\s*["\']2025-04-15/i')
            ->and($html)->toMatch('/April\s+15,?\s+2025|Apr\s+15,?\s+2025|2025-04-15/');
    });

    it('hides last updated date when post has not been modified', function (): void {
        $view = createBlogTestView();

        $post = createPostShowTestPost(
            publishedAt: '2025-03-20 14:30:00',
            updatedAt: null,
        );

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
        ]);

        expect($html)->not->toMatch('/updated/i');
    });

    it('displays categories as links', function (): void {
        $view = createBlogTestView();

        $post = createPostShowTestPost();
        $post->setCategories([
            createPostShowTestCategory(1, 'Technology', 'technology'),
            createPostShowTestCategory(2, 'Programming', 'programming'),
        ]);

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
        ]);

        expect($html)->toMatch(
            '/<a[^>]*href\s*=\s*["\'][^"\']*\/blog\/category\/technology["\'][^>]*>.*Technology.*<\/a>/s',
        )
            ->and($html)->toMatch(
                '/<a[^>]*href\s*=\s*["\'][^"\']*\/blog\/category\/programming["\'][^>]*>.*Programming.*<\/a>/s',
            );
    });

    it('displays tags as links', function (): void {
        $view = createBlogTestView();

        $post = createPostShowTestPost();
        $post->setTags([
            createPostShowTestTag(1, 'PHP', 'php'),
            createPostShowTestTag(2, 'Laravel', 'laravel'),
        ]);

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
        ]);

        expect($html)->toMatch('/<a[^>]*href\s*=\s*["\'][^"\']*\/blog\/tag\/php["\'][^>]*>.*PHP.*<\/a>/s')
            ->and($html)->toMatch('/<a[^>]*href\s*=\s*["\'][^"\']*\/blog\/tag\/laravel["\'][^>]*>.*Laravel.*<\/a>/s');
    });

    it('has placeholder for comment section', function (): void {
        $view = createBlogTestView();

        $post = createPostShowTestPost();

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
        ]);

        expect($html)->toMatch('/<section[^>]*class\s*=\s*["\'][^"\']*comments[^"\']*["\']/i')
            ->and($html)->toMatch('/comment/i');
    });

    it('has semantic HTML with article element', function (): void {
        $view = createBlogTestView();

        $post = createPostShowTestPost();

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
        ]);

        expect($html)->toMatch('/<main>/')
            ->and($html)->toMatch('/<article[^>]*class\s*=\s*["\'][^"\']*post-article[^"\']*["\']/i')
            ->and($html)->toMatch('/<header[^>]*class\s*=\s*["\'][^"\']*post-header[^"\']*["\']/i')
            ->and($html)->toMatch('/<footer[^>]*class\s*=\s*["\'][^"\']*post-footer[^"\']*["\']/i')
            ->and($html)->toMatch('/<div[^>]*class\s*=\s*["\'][^"\']*post-author[^"\']*["\']/i')
            ->and($html)->toMatch('/<time[^>]*datetime\s*=\s*["\']/i');
    });

    it('includes proper heading hierarchy', function (): void {
        $view = createBlogTestView();

        $post = createPostShowTestPost(title: 'Main Article Title');
        $post->setCategories([
            createPostShowTestCategory(1, 'Technology', 'technology'),
        ]);
        $post->setTags([
            createPostShowTestTag(1, 'PHP', 'php'),
        ]);

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
        ]);

        // h1 for main title, h2 only for Comments section
        // Categories and Tags use labels (not h2) as they are metadata, not content sections
        expect($html)->toMatch('/<h1[^>]*>.*Main Article Title.*<\/h1>/s')
            ->and($html)->toMatch('/<strong>Categories:<\/strong>/i')
            ->and($html)->toMatch('/<strong>Tags:<\/strong>/i')
            ->and($html)->toMatch('/<h2[^>]*id\s*=\s*["\']comments-heading["\'][^>]*>.*Comments.*<\/h2>/is');
    });

    it('includes comment thread component after post content', function (): void {
        $view = createBlogTestView();

        $post = createPostShowTestPost();
        $comment = createPostShowTestComment(
            id: 1,
            name: 'John Doe',
            content: 'Great article!',
        );

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
            'comments' => [$comment],
        ]);

        // Comment thread should render the comment author and content
        expect($html)->toContain('John Doe')
            ->and($html)->toContain('Great article!')
            // Comment should appear after post content (inside comments section)
            ->and($html)->toMatch('/<section[^>]*class\s*=\s*["\'][^"\']*post-comments[^"\']*["\']/');
    });

    it('includes comment form component', function (): void {
        $view = createBlogTestView();

        $post = createPostShowTestPost();

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
        ]);

        // Comment form should be present with proper structure
        expect($html)->toMatch('/<form[^>]*class\s*=\s*["\'][^"\']*comment-form[^"\']*["\']/i')
            ->and($html)->toMatch('/method\s*=\s*["\']post["\']/i')
            // Form should have name, email, and content fields
            ->and($html)->toMatch('/name\s*=\s*["\']name["\']/i')
            ->and($html)->toMatch('/name\s*=\s*["\']email["\']/i')
            ->and($html)->toMatch('/name\s*=\s*["\']content["\']/i');
    });

    it('passes verified comments to thread component', function (): void {
        $view = createBlogTestView();

        $post = createPostShowTestPost();
        $verifiedComment = createPostShowTestComment(
            id: 1,
            name: 'Verified User',
            content: 'This is a verified comment!',
        );
        $verifiedComment->verifiedAt = '2024-01-15 12:00:00';

        $anotherVerifiedComment = createPostShowTestComment(
            id: 2,
            name: 'Another Verified User',
            content: 'Another verified comment.',
        );
        $anotherVerifiedComment->verifiedAt = '2024-01-16 14:00:00';

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
            'comments' => [$verifiedComment, $anotherVerifiedComment],
        ]);

        // Verified comments should be passed to and rendered by thread component
        expect($html)->toContain('Verified User')
            ->and($html)->toContain('This is a verified comment!')
            ->and($html)->toContain('Another Verified User')
            ->and($html)->toContain('Another verified comment.');
    });

    it('passes post slug to form for action URL', function (): void {
        $view = createBlogTestView();

        $post = createPostShowTestPost(
            id: 42,
            slug: 'my-awesome-post',
        );

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
        ]);

        // Form action should use post slug for the URL
        expect($html)->toMatch('/action\s*=\s*["\'][^"\']*\/blog\/my-awesome-post\/comment["\']/i');
    });

    it('displays verification success message when present', function (): void {
        $view = createBlogTestView();

        $post = createPostShowTestPost();

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
            'verificationSuccess' => true,
        ]);

        // Should display a success message about comment verification
        expect($html)->toMatch('/comment.*verified|verified.*comment|thank\s+you/i');
    });

    it('shows comment count in heading', function (): void {
        $view = createBlogTestView();

        $post = createPostShowTestPost();
        $comment1 = createPostShowTestComment(
            id: 1,
            name: 'User One',
            content: 'First comment.',
        );
        $comment2 = createPostShowTestComment(
            id: 2,
            name: 'User Two',
            content: 'Second comment.',
        );

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
            'comments' => [$comment1, $comment2],
            'commentCount' => 2,
        ]);

        // Comment count should be displayed in the heading
        expect($html)->toMatch('/2\s+[Cc]omments/');
    });

    it('handles reply form display via JavaScript data attribute', function (): void {
        $view = createBlogTestView();

        $post = createPostShowTestPost();
        $comment = createPostShowTestComment(
            id: 123,
            name: 'Test User',
            content: 'A comment with a reply link.',
        );

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
            'comments' => [$comment],
        ]);

        // Reply link should have data-parent-id attribute for JavaScript handling
        expect($html)->toMatch('/data-parent-id\s*=\s*["\']123["\']/');
    });

    it('maintains proper section structure', function (): void {
        $view = createBlogTestView();

        $post = createPostShowTestPost();
        $comment = createPostShowTestComment(
            id: 1,
            name: 'Test User',
            content: 'A test comment.',
        );

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
            'comments' => [$comment],
            'commentCount' => 1,
        ]);

        // Verify proper section structure:
        // - Comments section with aria-labelledby
        // - h2 heading for Comments with id
        // - Comment thread before form
        // - Comment form at the end
        expect($html)->toMatch('/<section[^>]*class\s*=\s*["\'][^"\']*post-comments[^"\']*["\']/i')
            ->and($html)->toMatch('/<section[^>]*aria-labelledby\s*=\s*["\']comments-heading["\']/i')
            ->and($html)->toMatch('/<h2[^>]*id\s*=\s*["\']comments-heading["\'][^>]*>.*Comments.*<\/h2>/is')
            // Comment thread article should appear before form
            ->and($html)->toMatch('/<article[^>]*class\s*=\s*["\'][^"\']*comment\b[^"\']*["\']/i')
            ->and($html)->toMatch('/<form[^>]*class\s*=\s*["\'][^"\']*comment-form[^"\']*["\']/i');
    });
});

function createPostShowTestPost(
    int $id = 1,
    string $title = 'Test Post Title',
    string $slug = 'test-post-title',
    string $content = '<p>This is the full post content with multiple paragraphs.</p><p>Second paragraph here.</p>',
    ?string $summary = 'A brief summary of the post.',
    string $publishedAt = '2025-01-15 10:00:00',
    ?string $updatedAt = null,
    PostStatus $status = PostStatus::Published,
): Post {
    $post = new Post(
        title: $title,
        content: $content,
        authorId: 1,
        slug: $slug,
        summary: $summary,
    );
    $post->id = $id;
    $post->status = $status;
    $post->publishedAt = $publishedAt;
    $post->updatedAt = $updatedAt;

    $author = createPostShowTestAuthor();
    $post->setAuthor($author);

    return $post;
}

function createPostShowTestAuthor(
    int $id = 1,
    string $name = 'Jane Smith',
    string $email = 'jane@example.com',
    ?string $bio = 'Jane is a passionate tech writer with 10 years of experience.',
    string $slug = 'jane-smith',
): Author {
    $author = new Author();
    $author->id = $id;
    $author->name = $name;
    $author->email = $email;
    $author->bio = $bio;
    $author->slug = $slug;

    return $author;
}

function createPostShowTestCategory(
    int $id,
    string $name,
    string $slug,
): Category {
    $category = new Category();
    $category->id = $id;
    $category->name = $name;
    $category->slug = $slug;

    return $category;
}

function createPostShowTestTag(
    int $id,
    string $name,
    string $slug,
): Tag {
    $tag = new Tag();
    $tag->id = $id;
    $tag->name = $name;
    $tag->slug = $slug;

    return $tag;
}

function createPostShowTestComment(
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
