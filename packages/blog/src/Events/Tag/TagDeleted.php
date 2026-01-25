<?php

declare(strict_types=1);

namespace Marko\Blog\Events\Tag;

use DateTimeImmutable;
use Marko\Blog\Entity\TagInterface;
use Marko\Core\Event\Event;

class TagDeleted extends Event
{
    public function __construct(
        private readonly TagInterface $tag,
        private readonly DateTimeImmutable $timestamp,
    ) {}

    public function getTag(): TagInterface
    {
        return $this->tag;
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }
}
