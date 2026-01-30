<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Documentation;

describe('Blog module README.md', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $readme = fn () => file_get_contents($readmePath);

    it('has title and one-liner describing the module', function () use ($readme): void {
        $content = $readme();

        expect($content)->toContain('# Marko Blog')
            ->and($content)->toMatch('/WordPress-like blog.*posts.*authors.*categories.*tags.*comments/i');
    });

    it('has installation section with composer command', function () use ($readme): void {
        $content = $readme();

        expect($content)->toContain('## Installation')
            ->and($content)->toContain('composer require marko/blog');
    });

    it('documents view driver requirement and suggests marko/view-latte', function () use ($readme): void {
        $content = $readme();

        expect($content)->toContain('marko/view-latte')
            ->and($content)->toMatch('/view.*driver|template.*engine/i');
    });

    it('explains how to override view templates in app module', function () use ($readme): void {
        $content = $readme();

        expect($content)->toContain('### Overriding Templates')
            ->and($content)->toMatch('/app\/|app module/i');
    });

    it('explains how to use alternative view engines', function () use ($readme): void {
        $content = $readme();

        expect($content)->toMatch('/alternative.*view|different.*engine|blade|twig/i');
    });

    it('documents all configuration options with defaults', function () use ($readme): void {
        $content = $readme();

        expect($content)->toContain('## Configuration')
            ->and($content)->toContain('posts_per_page')
            ->and($content)->toContain('comment_max_depth')
            ->and($content)->toContain('comment_rate_limit_seconds')
            ->and($content)->toContain('verification_token_expiry_days')
            ->and($content)->toContain('route_prefix');
    });

    it('shows how to swap implementations via Preferences', function () use ($readme): void {
        $content = $readme();

        expect($content)->toMatch('/Preference|#\[Preference/i')
            ->and($content)->toMatch('/swap.*implementation|replace.*class|override/i');
    });

    it('shows how to hook methods via Plugins', function () use ($readme): void {
        $content = $readme();

        expect($content)->toContain('#[Plugin')
            ->and($content)->toMatch('/#\[Before\]|#\[After\]/');
    });

    it('shows how to react to events via Observers', function () use ($readme): void {
        $content = $readme();

        expect($content)->toContain('#[Observer')
            ->and($content)->toMatch('/react.*event|event.*observer/i');
    });

    it('lists all available lifecycle events', function () use ($readme): void {
        $content = $readme();

        expect($content)->toContain('## Available Events')
            ->and($content)->toContain('PostCreated')
            ->and($content)->toContain('PostUpdated')
            ->and($content)->toContain('PostPublished')
            ->and($content)->toContain('PostDeleted')
            ->and($content)->toContain('CommentCreated')
            ->and($content)->toContain('CommentVerified')
            ->and($content)->toContain('CommentDeleted')
            ->and($content)->toContain('CategoryCreated')
            ->and($content)->toContain('TagCreated')
            ->and($content)->toContain('AuthorCreated');
    });

    it('documents all public routes', function () use ($readme): void {
        $content = $readme();

        expect($content)->toContain('## Routes')
            ->and($content)->toContain('GET /blog')
            ->and($content)->toContain('GET /blog/{slug}')
            ->and($content)->toContain('GET /blog/category/{slug}')
            ->and($content)->toContain('GET /blog/tag/{slug}')
            ->and($content)->toContain('GET /blog/author/{slug}')
            ->and($content)->toContain('GET /blog/search')
            ->and($content)->toContain('POST /blog/{slug}/comment')
            ->and($content)->toContain('GET /blog/comment/verify/{token}');
    });

    it('includes CLI commands section', function () use ($readme): void {
        $content = $readme();

        expect($content)->toContain('## CLI Commands')
            ->and($content)->toContain('blog:publish-scheduled')
            ->and($content)->toContain('blog:cleanup');
    });
});
