<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Enum;

use Marko\Blog\Enum\PostStatus;

it('has draft status with value draft', function (): void {
    expect(PostStatus::Draft->value)->toBe('draft');
});

it('has published status with value published', function (): void {
    expect(PostStatus::Published->value)->toBe('published');
});

it('has scheduled status with value scheduled', function (): void {
    expect(PostStatus::Scheduled->value)->toBe('scheduled');
});

it('can be created from string value', function (): void {
    expect(PostStatus::from('draft'))->toBe(PostStatus::Draft)
        ->and(PostStatus::from('published'))->toBe(PostStatus::Published)
        ->and(PostStatus::from('scheduled'))->toBe(PostStatus::Scheduled);
});

it('returns null for invalid string value using tryFrom', function (): void {
    expect(PostStatus::tryFrom('invalid'))->toBeNull()
        ->and(PostStatus::tryFrom(''))->toBeNull()
        ->and(PostStatus::tryFrom('DRAFT'))->toBeNull();
});
