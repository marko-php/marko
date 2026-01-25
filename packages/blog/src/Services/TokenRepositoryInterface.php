<?php

declare(strict_types=1);

namespace Marko\Blog\Services;

use Marko\Blog\Entity\VerificationToken;

interface TokenRepositoryInterface
{
    public function save(
        VerificationToken $token,
    ): void;

    public function findByToken(
        string $token,
    ): ?VerificationToken;

    public function findByCommentId(
        int $commentId,
    ): ?VerificationToken;

    public function findBrowserTokenForEmail(
        string $email,
    ): ?VerificationToken;

    public function delete(
        VerificationToken $token,
    ): void;
}
