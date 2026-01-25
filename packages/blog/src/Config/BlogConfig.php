<?php

declare(strict_types=1);

namespace Marko\Blog\Config;

use Marko\Blog\Exceptions\InvalidRoutePrefixException;
use Marko\Config\ConfigRepositoryInterface;

readonly class BlogConfig implements BlogConfigInterface
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function getPostsPerPage(): int
    {
        return $this->config->getInt('blog.posts_per_page', 10);
    }

    public function getCommentMaxDepth(): int
    {
        return $this->config->getInt('blog.comment_max_depth', 5);
    }

    public function getCommentRateLimitSeconds(): int
    {
        return $this->config->getInt('blog.comment_rate_limit_seconds', 30);
    }

    public function getVerificationTokenExpiryDays(): int
    {
        return $this->config->getInt('blog.verification_token_expiry_days', 7);
    }

    public function getVerificationCookieDays(): int
    {
        return $this->config->getInt('blog.verification_cookie_days', 365);
    }

    public function getRoutePrefix(): string
    {
        $prefix = $this->config->getString('blog.route_prefix', '/blog');

        if (!str_starts_with($prefix, '/')) {
            throw InvalidRoutePrefixException::mustStartWithSlash($prefix);
        }

        if ($prefix !== '/' && str_ends_with($prefix, '/')) {
            throw InvalidRoutePrefixException::mustNotEndWithSlash($prefix);
        }

        return $prefix;
    }

    public function getVerificationCookieName(): string
    {
        return $this->config->getString('blog.verification_cookie_name', 'blog_verified');
    }
}
