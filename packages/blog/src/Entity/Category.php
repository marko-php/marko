<?php

declare(strict_types=1);

namespace Marko\Blog\Entity;

use DateTimeImmutable;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('categories')]
class Category extends Entity implements CategoryInterface
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $name;

    #[Column(unique: true)]
    public string $slug;

    #[Column('parent_id')]
    public ?int $parentId = null;

    #[Column('created_at')]
    public ?string $createdAt = null;

    #[Column('updated_at')]
    public ?string $updatedAt = null;

    private ?CategoryInterface $parent = null;

    /** @var array<CategoryInterface> */
    private array $children = [];

    /** @var array<CategoryInterface> */
    private array $path = [];

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

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function getParent(): ?CategoryInterface
    {
        return $this->parent;
    }

    public function setParent(
        ?CategoryInterface $parent,
    ): void {
        $this->parent = $parent;
    }

    /**
     * @return array<CategoryInterface>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * @param array<CategoryInterface> $children
     */
    public function setChildren(
        array $children,
    ): void {
        $this->children = $children;
    }

    /**
     * @return array<CategoryInterface>
     */
    public function getPath(): array
    {
        return $this->path;
    }

    /**
     * @param array<CategoryInterface> $path
     */
    public function setPath(
        array $path,
    ): void {
        $this->path = $path;
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
