<?php

declare(strict_types=1);

namespace Marko\Blog\Events\Comment;

use DateTimeImmutable;
use Marko\Blog\Entity\CommentInterface;
use Marko\Blog\Entity\PostInterface;
use Marko\Core\Event\Event;

class CommentVerified extends Event
{
    public function __construct(
        private readonly CommentInterface $comment,
        private readonly PostInterface $post,
        private readonly string $verificationMethod,
        private readonly DateTimeImmutable $timestamp = new DateTimeImmutable(),
    ) {}

    public function getComment(): CommentInterface
    {
        return $this->comment;
    }

    public function getPost(): PostInterface
    {
        return $this->post;
    }

    public function getVerificationMethod(): string
    {
        return $this->verificationMethod;
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }
}
