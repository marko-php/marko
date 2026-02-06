<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Entity;

use DateTimeImmutable;

interface PermissionInterface
{
    public function getId(): ?int;

    public function getKey(): string;

    public function getLabel(): string;

    public function getGroup(): string;

    public function getCreatedAt(): ?DateTimeImmutable;
}
