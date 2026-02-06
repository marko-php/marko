<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Entity;

use DateTimeImmutable;

interface RoleInterface
{
    public function getId(): ?int;

    public function getName(): string;

    public function getSlug(): string;

    public function getDescription(): ?string;

    public function isSuperAdmin(): bool;

    public function getCreatedAt(): ?DateTimeImmutable;

    public function getUpdatedAt(): ?DateTimeImmutable;
}
