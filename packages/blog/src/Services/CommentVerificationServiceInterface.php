<?php

declare(strict_types=1);

namespace Marko\Blog\Services;

use Marko\Blog\Dto\VerificationResult;
use Marko\Blog\Entity\CommentInterface;

interface CommentVerificationServiceInterface
{
    public function sendVerificationEmail(
        CommentInterface $comment,
    ): string;

    public function verifyByToken(
        string $token,
    ): VerificationResult;

    public function isBrowserTokenValid(
        string $browserToken,
        string $email,
    ): bool;

    public function shouldAutoApprove(
        string $email,
        ?string $browserToken,
    ): bool;

    public function getCookieName(): string;

    public function getCookieLifetimeDays(): int;
}
