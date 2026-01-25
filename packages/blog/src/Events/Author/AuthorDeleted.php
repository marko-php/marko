<?php

declare(strict_types=1);

namespace Marko\Blog\Events\Author;

use DateTimeImmutable;
use Marko\Blog\Entity\AuthorInterface;
use Marko\Core\Event\Event;

class AuthorDeleted extends Event
{
    public function __construct(
        private readonly AuthorInterface $author,
        private readonly DateTimeImmutable $timestamp,
    ) {}

    public function getAuthor(): AuthorInterface
    {
        return $this->author;
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }
}
