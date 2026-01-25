<?php

declare(strict_types=1);

namespace Marko\Blog\Entity;

use DateTimeImmutable;

interface CategoryInterface
{
    public function getId(): ?int;

    public function getName(): string;

    public function getSlug(): string;

    public function getParentId(): ?int;

    public function getParent(): ?CategoryInterface;

    public function getChildren(): array;

    public function getPath(): array;

    public function getCreatedAt(): ?DateTimeImmutable;

    public function getUpdatedAt(): ?DateTimeImmutable;
}
