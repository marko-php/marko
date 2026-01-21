<?php

declare(strict_types=1);

namespace Marko\Blog\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('posts')]
class Post extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $title;

    #[Column(unique: true)]
    public string $slug;

    #[Column(type: 'TEXT')]
    public string $content;

    #[Column('created_at')]
    public ?string $createdAt = null;

    #[Column('updated_at')]
    public ?string $updatedAt = null;
}
