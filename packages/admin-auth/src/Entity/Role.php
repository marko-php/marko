<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Entity;

use DateMalformedStringException;
use DateTimeImmutable;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('roles')]
class Role extends Entity implements RoleInterface
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $name;

    #[Column(unique: true)]
    public string $slug;

    #[Column(type: 'TEXT')]
    public ?string $description = null;

    #[Column(default: '0')]
    public string $isSuperAdmin = '0';

    #[Column]
    public ?string $createdAt = null;

    #[Column]
    public ?string $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isSuperAdmin(): bool
    {
        return $this->isSuperAdmin === '1';
    }

    /**
     * @throws DateMalformedStringException
     */
    public function getCreatedAt(): ?DateTimeImmutable
    {
        if ($this->createdAt === null) {
            return null;
        }

        return new DateTimeImmutable($this->createdAt);
    }

    /**
     * @throws DateMalformedStringException
     */
    public function getUpdatedAt(): ?DateTimeImmutable
    {
        if ($this->updatedAt === null) {
            return null;
        }

        return new DateTimeImmutable($this->updatedAt);
    }
}
