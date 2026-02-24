<?php

declare(strict_types=1);

use Marko\Blog\Config\BlogConfig;
use Marko\Blog\Exceptions\InvalidRoutePrefixException;
use Marko\Testing\Fake\FakeConfigRepository;

it('reads posts_per_page from config without fallback', function (): void {
    $config = new BlogConfig(new FakeConfigRepository([
        'blog.posts_per_page' => 15,
    ]));

    expect($config->getPostsPerPage())->toBe(15);
});

it('reads comment_max_depth from config without fallback', function (): void {
    $config = new BlogConfig(new FakeConfigRepository([
        'blog.comment_max_depth' => 3,
    ]));

    expect($config->getCommentMaxDepth())->toBe(3);
});

it('reads comment_rate_limit_seconds from config without fallback', function (): void {
    $config = new BlogConfig(new FakeConfigRepository([
        'blog.comment_rate_limit_seconds' => 60,
    ]));

    expect($config->getCommentRateLimitSeconds())->toBe(60);
});

it('reads verification_token_expiry_days from config without fallback', function (): void {
    $config = new BlogConfig(new FakeConfigRepository([
        'blog.verification_token_expiry_days' => 14,
    ]));

    expect($config->getVerificationTokenExpiryDays())->toBe(14);
});

it('reads verification_cookie_days from config without fallback', function (): void {
    $config = new BlogConfig(new FakeConfigRepository([
        'blog.verification_cookie_days' => 180,
    ]));

    expect($config->getVerificationCookieDays())->toBe(180);
});

it('reads verification_cookie_name from config without fallback', function (): void {
    $config = new BlogConfig(new FakeConfigRepository([
        'blog.verification_cookie_name' => 'custom_cookie',
    ]));

    expect($config->getVerificationCookieName())->toBe('custom_cookie');
});

it('reads route_prefix from config without fallback', function (): void {
    $config = new BlogConfig(new FakeConfigRepository([
        'blog.route_prefix' => '/articles',
    ]));

    expect($config->getRoutePrefix())->toBe('/articles');
});

it('reads site_name from config without fallback', function (): void {
    $config = new BlogConfig(new FakeConfigRepository([
        'blog.site_name' => 'My Awesome Blog',
    ]));

    expect($config->getSiteName())->toBe('My Awesome Blog');
});

it('validates route_prefix starts with forward slash', function (): void {
    $config = new BlogConfig(new FakeConfigRepository([
        'blog.route_prefix' => 'blog',
    ]));

    expect(fn () => $config->getRoutePrefix())
        ->toThrow(InvalidRoutePrefixException::class, 'must start with a forward slash');
});

it('validates route_prefix does not end with forward slash', function (): void {
    $config = new BlogConfig(new FakeConfigRepository([
        'blog.route_prefix' => '/blog/',
    ]));

    expect(fn () => $config->getRoutePrefix())
        ->toThrow(InvalidRoutePrefixException::class, 'must not end with a forward slash');
});

it('config file contains all required keys with defaults', function (): void {
    $configPath = dirname(__DIR__, 2) . '/config/blog.php';

    expect(file_exists($configPath))->toBeTrue();

    $configData = require $configPath;

    expect($configData)->toBeArray()
        ->and($configData)->toHaveKey('site_name')
        ->and($configData)->toHaveKey('posts_per_page')
        ->and($configData)->toHaveKey('comment_max_depth')
        ->and($configData)->toHaveKey('comment_rate_limit_seconds')
        ->and($configData)->toHaveKey('verification_token_expiry_days')
        ->and($configData)->toHaveKey('verification_cookie_days')
        ->and($configData)->toHaveKey('verification_cookie_name')
        ->and($configData)->toHaveKey('route_prefix')
        // Verify default values
        ->and($configData['site_name'])->toBe('My Blog')
        ->and($configData['posts_per_page'])->toBe(10)
        ->and($configData['comment_max_depth'])->toBe(5)
        ->and($configData['comment_rate_limit_seconds'])->toBe(30)
        ->and($configData['verification_token_expiry_days'])->toBe(7)
        ->and($configData['verification_cookie_days'])->toBe(365)
        ->and($configData['verification_cookie_name'])->toBe('blog_verified')
        ->and($configData['route_prefix'])->toBe('/blog');
});
