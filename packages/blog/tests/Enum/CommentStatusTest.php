<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Enum;

use Marko\Blog\Enum\CommentStatus;

it('has pending status with value pending', function (): void {
    expect(CommentStatus::Pending->value)->toBe('pending');
});

it('has verified status with value verified', function (): void {
    expect(CommentStatus::Verified->value)->toBe('verified');
});

it('can be created from string value', function (): void {
    expect(CommentStatus::from('pending'))->toBe(CommentStatus::Pending)
        ->and(CommentStatus::from('verified'))->toBe(CommentStatus::Verified);
});

it('returns null for invalid string value using tryFrom', function (): void {
    expect(CommentStatus::tryFrom('invalid'))->toBeNull()
        ->and(CommentStatus::tryFrom(''))->toBeNull()
        ->and(CommentStatus::tryFrom('PENDING'))->toBeNull();
});
