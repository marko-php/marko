<?php

declare(strict_types=1);

namespace Marko\Blog\Events\Category;

use DateTimeImmutable;
use Marko\Blog\Entity\CategoryInterface;
use Marko\Core\Event\Event;

class CategoryCreated extends Event
{
    public function __construct(
        public readonly CategoryInterface $category,
        public readonly ?CategoryInterface $parent = null,
        public readonly DateTimeImmutable $timestamp = new DateTimeImmutable(),
    ) {}

    public function getCategory(): CategoryInterface
    {
        return $this->category;
    }

    public function getParent(): ?CategoryInterface
    {
        return $this->parent;
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }
}
