<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Entity;

use DateMalformedStringException;
use DateTimeImmutable;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('permissions')]
class Permission extends Entity implements PermissionInterface
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(unique: true)]
    public string $key;

    #[Column]
    public string $label;

    #[Column]
    public string $group;

    #[Column]
    public ?string $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getGroup(): string
    {
        return $this->group;
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
}
