<?php

declare(strict_types=1);

namespace Marko\Blog\Entity;

use DateTimeImmutable;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('authors')]
#[Index('idx_authors_email', ['email'], unique: true)]
class Author extends Entity implements AuthorInterface
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $name;

    #[Column]
    public string $email;

    #[Column(type: 'TEXT')]
    public ?string $bio = null;

    #[Column(unique: true)]
    public string $slug;

    #[Column('created_at')]
    public ?string $createdAt = null;

    #[Column('updated_at')]
    public ?string $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        if ($this->createdAt === null) {
            return null;
        }

        return new DateTimeImmutable($this->createdAt);
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        if ($this->updatedAt === null) {
            return null;
        }

        return new DateTimeImmutable($this->updatedAt);
    }
}
