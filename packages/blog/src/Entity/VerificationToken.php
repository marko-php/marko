<?php

declare(strict_types=1);

namespace Marko\Blog\Entity;

use DateTimeImmutable;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('verification_tokens')]
#[Index('idx_verification_tokens_email', ['email'])]
#[Index('idx_verification_tokens_comment_id', ['comment_id'])]
class VerificationToken extends Entity implements VerificationTokenInterface
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(unique: true)]
    public string $token;

    #[Column]
    public string $email;

    #[Column]
    public string $type;

    #[Column('comment_id', references: 'comments.id', onDelete: 'CASCADE')]
    public ?int $commentId = null;

    #[Column('created_at')]
    public ?DateTimeImmutable $createdAt = null;

    #[Column('expires_at')]
    public ?DateTimeImmutable $expiresAt = null;

    public static function create(
        string $email,
        string $type,
        ?int $commentId = null,
        ?DateTimeImmutable $expiresAt = null,
    ): self {
        $instance = new self();
        $instance->token = bin2hex(random_bytes(32));
        $instance->email = $email;
        $instance->type = $type;
        $instance->commentId = $commentId;
        $instance->createdAt = new DateTimeImmutable();
        $instance->expiresAt = $expiresAt;

        return $instance;
    }

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new DateTimeImmutable();
    }
}
