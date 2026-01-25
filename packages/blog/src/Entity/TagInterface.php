<?php

declare(strict_types=1);

namespace Marko\Blog\Entity;

use DateTimeImmutable;

interface TagInterface
{
    public function getId(): ?int;

    public function getName(): string;

    public function getSlug(): string;

    public function getCreatedAt(): ?DateTimeImmutable;

    public function getUpdatedAt(): ?DateTimeImmutable;
}
