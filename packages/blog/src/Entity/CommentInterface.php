<?php

declare(strict_types=1);

namespace Marko\Blog\Entity;

use DateTimeImmutable;

interface CommentInterface
{
    public function getParent(): ?CommentInterface;

    /**
     * @return array<CommentInterface>
     */
    public function getChildren(): array;

    public function getPost(): PostInterface;

    public function getVerifiedAt(): ?DateTimeImmutable;

    public function getCreatedAt(): ?DateTimeImmutable;

    public function getName(): string;
}
