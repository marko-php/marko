<?php

declare(strict_types=1);

namespace Marko\Blog\Entity;

use DateTimeImmutable;
use Marko\Blog\Enum\PostStatus;

interface PostInterface
{
    public function getId(): ?int;

    public function getTitle(): string;

    public function getSlug(): string;

    public function getContent(): string;

    public function getSummary(): ?string;

    public function getStatus(): PostStatus;

    public function getAuthorId(): int;

    public function getAuthor(): AuthorInterface;

    public function getScheduledAt(): ?DateTimeImmutable;

    public function getPublishedAt(): ?DateTimeImmutable;

    public function getCreatedAt(): ?DateTimeImmutable;

    public function getUpdatedAt(): ?DateTimeImmutable;

    public function wasUpdatedAfterPublishing(): bool;

    public function isPublished(): bool;

    public function isDraft(): bool;

    public function isScheduled(): bool;
}
