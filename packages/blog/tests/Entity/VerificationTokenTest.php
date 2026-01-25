<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Entity;

use DateTimeImmutable;
use Marko\Blog\Entity\VerificationToken;

it('creates token with random secure value', function (): void {
    $token1 = VerificationToken::create(
        email: 'test@example.com',
        type: 'email',
    );

    $token2 = VerificationToken::create(
        email: 'test@example.com',
        type: 'email',
    );

    // Token should be a non-empty string
    expect($token1->token)->toBeString()
        ->and($token1->token)->not->toBeEmpty()
        // Tokens should be unique (random)
        ->and($token1->token)->not->toBe($token2->token)
        // Token should be sufficiently long for security (at least 32 characters)
        ->and(strlen($token1->token))->toBeGreaterThanOrEqual(32);
});

it('associates token with email address', function (): void {
    $email = 'commenter@example.com';
    $token = VerificationToken::create(
        email: $email,
        type: 'email',
    );

    expect($token->email)->toBe($email);
});

it('associates token with comment_id for email verification', function (): void {
    $commentId = 42;
    $token = VerificationToken::create(
        email: 'test@example.com',
        type: 'email',
        commentId: $commentId,
    );

    expect($token->commentId)->toBe($commentId);
});

it('has type field distinguishing email vs browser tokens', function (): void {
    $emailToken = VerificationToken::create(
        email: 'test@example.com',
        type: 'email',
    );

    $browserToken = VerificationToken::create(
        email: 'test@example.com',
        type: 'browser',
    );

    expect($emailToken->type)->toBe('email')
        ->and($browserToken->type)->toBe('browser');
});

it('has created_at timestamp', function (): void {
    $beforeCreate = new DateTimeImmutable();
    $token = VerificationToken::create(
        email: 'test@example.com',
        type: 'email',
    );
    $afterCreate = new DateTimeImmutable();

    expect($token->createdAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($token->createdAt)->toBeGreaterThanOrEqual($beforeCreate)
        ->and($token->createdAt)->toBeLessThanOrEqual($afterCreate);
});

it('has expires_at timestamp', function (): void {
    $expiresAt = new DateTimeImmutable('+1 hour');
    $token = VerificationToken::create(
        email: 'test@example.com',
        type: 'email',
        expiresAt: $expiresAt,
    );

    expect($token->expiresAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($token->expiresAt)->toBe($expiresAt);
});

it('checks if token is expired', function (): void {
    // Token with future expiration is not expired
    $validToken = VerificationToken::create(
        email: 'test@example.com',
        type: 'email',
        expiresAt: new DateTimeImmutable('+1 hour'),
    );

    // Token with past expiration is expired
    $expiredToken = VerificationToken::create(
        email: 'test@example.com',
        type: 'email',
        expiresAt: new DateTimeImmutable('-1 hour'),
    );

    // Token with no expiration is not expired
    $noExpirationToken = VerificationToken::create(
        email: 'test@example.com',
        type: 'email',
    );

    expect($validToken->isExpired())->toBeFalse()
        ->and($expiredToken->isExpired())->toBeTrue()
        ->and($noExpirationToken->isExpired())->toBeFalse();
});
