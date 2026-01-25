<?php

declare(strict_types=1);

namespace Marko\Blog\Entity;

use DateTimeImmutable;

interface AuthorInterface
{
    public function getId(): ?int;

    public function getName(): string;

    public function getEmail(): string;

    public function getBio(): ?string;

    public function getSlug(): string;

    public function getCreatedAt(): ?DateTimeImmutable;

    public function getUpdatedAt(): ?DateTimeImmutable;
}
