<?php

declare(strict_types=1);

namespace Marko\Blog\Entity;

use DateTimeImmutable;
use InvalidArgumentException;
use Marko\Blog\Enum\CommentStatus;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('comments')]
#[Index('idx_comments_post_id', ['post_id'])]
#[Index('idx_comments_status', ['status'])]
#[Index('idx_comments_parent_id', ['parent_id'])]
#[Index('idx_comments_email', ['email'])]
class Comment extends Entity implements CommentInterface
{
    public const int MAX_CONTENT_LENGTH = 10000;

    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column('post_id', references: 'posts.id', onDelete: 'CASCADE')]
    public int $postId;

    #[Column]
    public string $name;

    #[Column]
    public string $email;

    #[Column(type: 'TEXT')]
    public string $content;

    #[Column]
    public CommentStatus $status = CommentStatus::Pending;

    #[Column('parent_id', references: 'comments.id', onDelete: 'CASCADE')]
    public ?int $parentId = null;

    #[Column('verified_at')]
    public ?string $verifiedAt = null;

    #[Column('created_at')]
    public ?string $createdAt = null;

    private ?PostInterface $post = null;

    private ?CommentInterface $parent = null;

    /** @var array<CommentInterface> */
    private array $children = [];

    public function getParent(): ?CommentInterface
    {
        return $this->parent;
    }

    public function setParent(
        ?CommentInterface $parent,
    ): void {
        $this->parent = $parent;
    }

    /**
     * @return array<CommentInterface>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * @param array<CommentInterface> $children
     */
    public function setChildren(
        array $children,
    ): void {
        $this->children = $children;
    }

    public function getPost(): PostInterface
    {
        if ($this->post === null) {
            throw new InvalidArgumentException('Post has not been loaded');
        }

        return $this->post;
    }

    public function setPost(
        PostInterface $post,
    ): void {
        $this->post = $post;
    }

    public function getVerifiedAt(): ?DateTimeImmutable
    {
        if ($this->verifiedAt === null) {
            return null;
        }

        return new DateTimeImmutable($this->verifiedAt);
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        if ($this->createdAt === null) {
            return null;
        }

        return new DateTimeImmutable($this->createdAt);
    }

    public function getName(): string
    {
        return $this->name;
    }
}
