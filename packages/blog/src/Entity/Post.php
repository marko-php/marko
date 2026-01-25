<?php

declare(strict_types=1);

namespace Marko\Blog\Entity;

use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Services\SlugGeneratorInterface;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('posts')]
class Post extends Entity implements PostInterface
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(unique: true)]
    public string $slug;

    #[Column]
    public PostStatus $status = PostStatus::Draft;

    #[Column('scheduled_at')]
    public ?string $scheduledAt = null;

    #[Column('published_at')]
    public ?string $publishedAt = null;

    #[Column('created_at')]
    public ?string $createdAt = null;

    #[Column('updated_at')]
    public ?string $updatedAt = null;

    private ?AuthorInterface $author = null;

    /**
     * @param Closure(string): bool|null $uniquenessChecker
     */
    public function __construct(
        #[Column]
        public string $title = '',
        #[Column(type: 'TEXT')]
        public string $content = '',
        #[Column('author_id')]
        public int $authorId = 0,
        ?SlugGeneratorInterface $slugGenerator = null,
        ?string $slug = null,
        #[Column(type: 'TEXT')]
        public ?string $summary = null,
        ?Closure $uniquenessChecker = null,
    ) {
        if ($slug !== null) {
            $this->slug = $slug;
        } elseif ($slugGenerator !== null && $this->title !== '') {
            $this->slug = $slugGenerator->generate($this->title, $uniquenessChecker);
        } else {
            $this->slug = '';
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function getStatus(): PostStatus
    {
        return $this->status;
    }

    public function setStatus(
        PostStatus $status,
    ): void {
        if ($status === PostStatus::Scheduled && $this->scheduledAt === null) {
            throw new InvalidArgumentException('Scheduled posts must have a scheduled_at date');
        }

        $this->status = $status;

        if ($status === PostStatus::Published && $this->publishedAt === null) {
            $this->publishedAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        }
    }

    public function getAuthorId(): int
    {
        return $this->authorId;
    }

    public function getAuthor(): AuthorInterface
    {
        if ($this->author === null) {
            throw new InvalidArgumentException('Author has not been loaded');
        }

        return $this->author;
    }

    public function setAuthor(
        AuthorInterface $author,
    ): void {
        $this->author = $author;
    }

    public function getScheduledAt(): ?DateTimeImmutable
    {
        if ($this->scheduledAt === null) {
            return null;
        }

        return new DateTimeImmutable($this->scheduledAt);
    }

    public function setScheduledAt(
        DateTimeImmutable $scheduledAt,
    ): void {
        $this->scheduledAt = $scheduledAt->format('Y-m-d H:i:s');
    }

    public function getPublishedAt(): ?DateTimeImmutable
    {
        if ($this->publishedAt === null) {
            return null;
        }

        return new DateTimeImmutable($this->publishedAt);
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

    public function wasUpdatedAfterPublishing(): bool
    {
        if ($this->publishedAt === null || $this->updatedAt === null) {
            return false;
        }

        $publishedAt = new DateTimeImmutable($this->publishedAt);
        $updatedAt = new DateTimeImmutable($this->updatedAt);

        return $updatedAt > $publishedAt;
    }

    public function isPublished(): bool
    {
        return $this->status === PostStatus::Published;
    }

    public function isDraft(): bool
    {
        return $this->status === PostStatus::Draft;
    }

    public function isScheduled(): bool
    {
        return $this->status === PostStatus::Scheduled;
    }
}
