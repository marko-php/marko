<?php

declare(strict_types=1);

namespace Marko\Blog\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use Marko\Blog\Config\BlogConfigInterface;
use Marko\Blog\Dto\VerificationResult;
use Marko\Blog\Entity\CommentInterface;
use Marko\Blog\Entity\VerificationToken;
use Marko\Blog\Enum\CommentStatus;
use Marko\Blog\Events\Comment\CommentVerified;
use Marko\Blog\Repositories\CommentRepositoryInterface;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Message;

readonly class CommentVerificationService implements CommentVerificationServiceInterface
{
    public function __construct(
        private TokenRepositoryInterface $tokenRepository,
        private CommentRepositoryInterface $commentRepository,
        private PostRepositoryInterface $postRepository,
        private MailerInterface $mailer,
        private BlogConfigInterface $config,
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {}

    public function sendVerificationEmail(
        CommentInterface $comment,
    ): string {
        // Delete any existing token for this comment
        $existingToken = $this->tokenRepository->findByCommentId($comment->id);
        if ($existingToken !== null) {
            $this->tokenRepository->delete($existingToken);
        }

        $expiresAt = new DateTimeImmutable(
            '+' . $this->config->getVerificationTokenExpiryDays() . ' days',
        );

        $token = VerificationToken::create(
            email: $comment->email,
            type: 'email',
            commentId: $comment->id,
            expiresAt: $expiresAt,
        );

        $this->tokenRepository->save($token);

        $verificationLink = '/blog/comment/verify?token=' . $token->token;

        $message = Message::create()
            ->to($comment->email, $comment->getName())
            ->subject('Verify your comment')
            ->text('Please verify your comment by clicking this link: ' . $verificationLink);

        $this->mailer->send($message);

        return $token->token;
    }

    public function verifyByToken(
        string $token,
    ): VerificationResult {
        $verificationToken = $this->tokenRepository->findByToken($token);

        if ($verificationToken === null) {
            throw new InvalidArgumentException('Invalid verification token');
        }

        if ($verificationToken->isExpired()) {
            throw new InvalidArgumentException('Verification token has expired');
        }

        $comment = $this->commentRepository->find($verificationToken->commentId);

        if ($comment === null) {
            throw new InvalidArgumentException('Comment not found');
        }

        $post = $this->postRepository->find($comment->postId);

        if ($post === null) {
            throw new InvalidArgumentException('Post not found');
        }

        // Update comment status to verified
        $comment->status = CommentStatus::Verified;
        $comment->verifiedAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $comment->setPost($post);
        $this->commentRepository->save($comment);

        // Dispatch event
        $this->markAsVerified($comment, 'email');

        // Create browser token for auto-approval
        $browserToken = VerificationToken::create(
            email: $verificationToken->email,
            type: 'browser',
        );
        $this->tokenRepository->save($browserToken);

        // Delete the used email verification token
        $this->tokenRepository->delete($verificationToken);

        return new VerificationResult(
            browserToken: $browserToken->token,
            postSlug: $post->getSlug(),
            commentId: $comment->id,
        );
    }

    public function isBrowserTokenValid(
        string $browserToken,
        string $email,
    ): bool {
        $token = $this->tokenRepository->findByToken($browserToken);

        if ($token === null) {
            return false;
        }

        if ($token->type !== 'browser') {
            return false;
        }

        return $token->email === $email;
    }

    public function shouldAutoApprove(
        string $email,
        ?string $browserToken,
    ): bool {
        if ($browserToken === null) {
            return false;
        }

        return $this->isBrowserTokenValid($browserToken, $email);
    }

    public function getCookieName(): string
    {
        return $this->config->getVerificationCookieName();
    }

    public function getCookieLifetimeDays(): int
    {
        return $this->config->getVerificationCookieDays();
    }

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
