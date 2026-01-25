<?php

declare(strict_types=1);

namespace Marko\Blog\Services;

use Marko\Blog\Entity\CommentInterface;
use Marko\Blog\Events\Comment\CommentVerified;
use Marko\Core\Event\EventDispatcherInterface;

class CommentVerificationService
{
    public function __construct(
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {}

    /**
     * Mark a comment as verified and dispatch the CommentVerified event.
     */
    public function markAsVerified(
        CommentInterface $comment,
        string $verificationMethod,
    ): void {
        $this->eventDispatcher?->dispatch(new CommentVerified(
            comment: $comment,
            post: $comment->getPost(),
            verificationMethod: $verificationMethod,
        ));
    }
}
