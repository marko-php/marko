<?php

declare(strict_types=1);

namespace Marko\Blog\Events\Post;

use DateTimeImmutable;
use Marko\Blog\Entity\PostInterface;
use Marko\Core\Event\Event;

class PostUpdated extends Event
{
    public function __construct(
        private readonly PostInterface $post,
        private readonly DateTimeImmutable $timestamp = new DateTimeImmutable(),
    ) {}

    public function getPost(): PostInterface
    {
        return $this->post;
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }
}
