<?php

declare(strict_types=1);

namespace Marko\Blog\Config;

interface BlogConfigInterface
{
    public function getPostsPerPage(): int;

    public function getCommentMaxDepth(): int;

    public function getCommentRateLimitSeconds(): int;

    public function getVerificationTokenExpiryDays(): int;

    public function getVerificationCookieDays(): int;

    public function getRoutePrefix(): string;

    public function getVerificationCookieName(): string;
}
