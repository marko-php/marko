<?php

declare(strict_types=1);

use Marko\Blog\Config\BlogConfig;
use Marko\Blog\Exceptions\InvalidRoutePrefixException;
use Marko\Config\ConfigRepositoryInterface;

function createBlogMockConfigRepository(
    array $configData = [],
): ConfigRepositoryInterface {
    return new readonly class ($configData) implements ConfigRepositoryInterface
    {
        public function __construct(
            private array $data,
        ) {}

        public function get(
            string $key,
            mixed $default = null,
            ?string $scope = null,
        ): mixed {
            return $this->data[$key] ?? $default;
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return isset($this->data[$key]);
        }

        public function getString(
            string $key,
            ?string $default = null,
            ?string $scope = null,
        ): string {
            return (string) ($this->data[$key] ?? $default);
        }

        public function getInt(
            string $key,
            ?int $default = null,
            ?string $scope = null,
        ): int {
            return (int) ($this->data[$key] ?? $default);
        }

        public function getBool(
            string $key,
            ?bool $default = null,
            ?string $scope = null,
        ): bool {
            return (bool) ($this->data[$key] ?? $default);
        }

        public function getFloat(
            string $key,
            ?float $default = null,
            ?string $scope = null,
        ): float {
            return (float) ($this->data[$key] ?? $default);
        }

        public function getArray(
            string $key,
            ?array $default = null,
            ?string $scope = null,
        ): array {
            return (array) ($this->data[$key] ?? $default ?? []);
        }

        public function all(
            ?string $scope = null,
        ): array {
            return $this->data;
        }

        public function withScope(
            string $scope,
        ): ConfigRepositoryInterface {
            return $this;
        }
    };
}

it('provides default posts_per_page value of 10', function (): void {
    $config = new BlogConfig(createBlogMockConfigRepository());

    expect($config->getPostsPerPage())->toBe(10);
});

it('provides default comment_max_depth value of 5', function (): void {
    $config = new BlogConfig(createBlogMockConfigRepository());

    expect($config->getCommentMaxDepth())->toBe(5);
});

it('provides default comment_rate_limit_seconds value of 30', function (): void {
    $config = new BlogConfig(createBlogMockConfigRepository());

    expect($config->getCommentRateLimitSeconds())->toBe(30);
});

it('provides default verification_token_expiry_days value of 7', function (): void {
    $config = new BlogConfig(createBlogMockConfigRepository());

    expect($config->getVerificationTokenExpiryDays())->toBe(7);
});

it('provides default verification_cookie_days value of 365', function (): void {
    $config = new BlogConfig(createBlogMockConfigRepository());

    expect($config->getVerificationCookieDays())->toBe(365);
});

it('provides default route_prefix value of /blog', function (): void {
    $config = new BlogConfig(createBlogMockConfigRepository());

    expect($config->getRoutePrefix())->toBe('/blog');
});

it('provides default verification_cookie_name value of blog_verified', function (): void {
    $config = new BlogConfig(createBlogMockConfigRepository());

    expect($config->getVerificationCookieName())->toBe('blog_verified');
});

it('allows configuration values to be overridden', function (): void {
    $config = new BlogConfig(createBlogMockConfigRepository([
        'blog.posts_per_page' => 25,
        'blog.comment_max_depth' => 3,
        'blog.comment_rate_limit_seconds' => 60,
        'blog.verification_token_expiry_days' => 14,
        'blog.verification_cookie_days' => 180,
        'blog.route_prefix' => '/articles',
        'blog.verification_cookie_name' => 'custom_verified',
    ]));

    expect($config->getPostsPerPage())->toBe(25)
        ->and($config->getCommentMaxDepth())->toBe(3)
        ->and($config->getCommentRateLimitSeconds())->toBe(60)
        ->and($config->getVerificationTokenExpiryDays())->toBe(14)
        ->and($config->getVerificationCookieDays())->toBe(180)
        ->and($config->getRoutePrefix())->toBe('/articles')
        ->and($config->getVerificationCookieName())->toBe('custom_verified');
});

it('validates route_prefix starts with forward slash', function (): void {
    $config = new BlogConfig(createBlogMockConfigRepository([
        'blog.route_prefix' => 'blog',
    ]));

    expect(fn () => $config->getRoutePrefix())
        ->toThrow(InvalidRoutePrefixException::class, 'must start with a forward slash');
});

it('validates route_prefix does not end with forward slash', function (): void {
    $config = new BlogConfig(createBlogMockConfigRepository([
        'blog.route_prefix' => '/blog/',
    ]));

    expect(fn () => $config->getRoutePrefix())
        ->toThrow(InvalidRoutePrefixException::class, 'must not end with a forward slash');
});

it('provides default configuration file', function (): void {
    $configPath = dirname(__DIR__, 2) . '/config/blog.php';

    expect(file_exists($configPath))->toBeTrue()
        ->and(is_array(require $configPath))->toBeTrue();
});
