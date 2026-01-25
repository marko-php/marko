<?php

declare(strict_types=1);

namespace Marko\Blog\Events\Post;

use DateTimeImmutable;
use Marko\Blog\Entity\PostInterface;
use Marko\Blog\Enum\PostStatus;
use Marko\Core\Event\Event;

class PostScheduled extends Event
{
    public function __construct(
        private readonly PostInterface $post,
        private readonly PostStatus $previousStatus,
        private readonly DateTimeImmutable $timestamp = new DateTimeImmutable(),
    ) {}

    public function getPost(): PostInterface
    {
        return $this->post;
    }

    public function getPreviousStatus(): PostStatus
    {
        return $this->previousStatus;
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }
}
