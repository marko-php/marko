<?php

declare(strict_types=1);

use Marko\Blog\Entity\Author;
use Marko\Blog\Entity\Category;
use Marko\Blog\Entity\Post;
use Marko\Blog\Entity\Tag;
use Marko\Blog\Enum\PostStatus;
use Marko\Config\ConfigRepository;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\Latte\LatteView;
use Marko\View\ModuleTemplateResolver;
use Marko\View\ViewConfig;

describe('Post Show View', function (): void {
    it('renders post title', function (): void {
        $view = createPostShowTestView();

        $post = createPostShowTestPost(title: 'My Amazing Blog Post');

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
        ]);

        expect($html)->toMatch('/<h1[^>]*>.*My Amazing Blog Post.*<\/h1>/s');
    });

    it('renders full post content', function (): void {
        $view = createPostShowTestView();

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
        $view = createPostShowTestView();

        $author = createPostShowTestAuthor(name: 'John Doe', slug: 'john-doe');
        $post = createPostShowTestPost();
        $post->setAuthor($author);

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
        ]);

        expect($html)->toContain('John Doe')
            ->and($html)->toMatch(
                '/<a[^>]*href\s*=\s*["\'][^"\']*\/blog\/author\/john-doe["\'][^>]*>.*John Doe.*<\/a>/s'
            );
    });

    it('displays author bio', function (): void {
        $view = createPostShowTestView();

        $author = createPostShowTestAuthor(bio: 'A passionate developer and tech writer.');
        $post = createPostShowTestPost();
        $post->setAuthor($author);

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
        ]);

        expect($html)->toContain('A passionate developer and tech writer.');
    });

    it('displays published date', function (): void {
        $view = createPostShowTestView();

        $post = createPostShowTestPost(publishedAt: '2025-03-20 14:30:00');

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
        ]);

        expect($html)->toMatch('/<time[^>]*datetime\s*=\s*["\']2025-03-20/i')
            ->and($html)->toMatch('/March\s+20,?\s+2025|Mar\s+20,?\s+2025|2025-03-20/');
    });

    it('displays last updated date when updated_at is after published_at', function (): void {
        $view = createPostShowTestView();

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
        $view = createPostShowTestView();

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
        $view = createPostShowTestView();

        $post = createPostShowTestPost();
        $post->setCategories([
            createPostShowTestCategory(1, 'Technology', 'technology'),
            createPostShowTestCategory(2, 'Programming', 'programming'),
        ]);

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
        ]);

        expect($html)->toMatch(
            '/<a[^>]*href\s*=\s*["\'][^"\']*\/blog\/category\/technology["\'][^>]*>.*Technology.*<\/a>/s'
        )
            ->and($html)->toMatch(
                '/<a[^>]*href\s*=\s*["\'][^"\']*\/blog\/category\/programming["\'][^>]*>.*Programming.*<\/a>/s'
            );
    });

    it('displays tags as links', function (): void {
        $view = createPostShowTestView();

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
        $view = createPostShowTestView();

        $post = createPostShowTestPost();

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
        ]);

        expect($html)->toMatch('/<section[^>]*class\s*=\s*["\'][^"\']*comments[^"\']*["\']/i')
            ->and($html)->toMatch('/comment/i');
    });

    it('has semantic HTML with article element', function (): void {
        $view = createPostShowTestView();

        $post = createPostShowTestPost();

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
        ]);

        expect($html)->toMatch('/<article[^>]*class\s*=\s*["\'][^"\']*post-article[^"\']*["\']/i')
            ->and($html)->toMatch('/<header[^>]*class\s*=\s*["\'][^"\']*post-header[^"\']*["\']/i')
            ->and($html)->toMatch('/<aside[^>]*class\s*=\s*["\'][^"\']*post-author[^"\']*["\']/i')
            ->and($html)->toMatch('/<time[^>]*datetime\s*=\s*["\']/i');
    });

    it('includes proper heading hierarchy', function (): void {
        $view = createPostShowTestView();

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

        // h1 for main title, h2 for section headings
        expect($html)->toMatch('/<h1[^>]*>.*Main Article Title.*<\/h1>/s')
            ->and($html)->toMatch('/<h2[^>]*>.*Categories.*<\/h2>/is')
            ->and($html)->toMatch('/<h2[^>]*>.*Tags.*<\/h2>/is')
            ->and($html)->toMatch('/<h2[^>]*>.*Comments.*<\/h2>/is');
    });
});

function createPostShowTestView(): LatteView
{
    $blogPackagePath = dirname(__DIR__, 2);
    $tempCacheDir = sys_get_temp_dir() . '/marko-post-show-test-' . uniqid();
    mkdir($tempCacheDir, 0755, true);

    $config = new ConfigRepository([
        'view' => [
            'cache_directory' => $tempCacheDir,
            'extension' => '.latte',
            'auto_refresh' => true,
            'strict_types' => true,
        ],
    ]);

    $moduleRepository = new ModuleRepository([
        new ModuleManifest(
            name: 'marko/blog',
            version: '1.0.0',
            path: $blogPackagePath,
            source: 'vendor',
        ),
    ]);

    $viewConfig = new ViewConfig($config);
    $templateResolver = new ModuleTemplateResolver($moduleRepository, $viewConfig);
    $engineFactory = new LatteEngineFactory($viewConfig);
    $engine = $engineFactory->create();

    return new LatteView($engine, $templateResolver);
}

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
