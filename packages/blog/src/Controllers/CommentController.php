<?php

declare(strict_types=1);

namespace Marko\Blog\Controllers;

use Marko\Blog\Config\BlogConfigInterface;
use Marko\Blog\Entity\Comment;
use Marko\Blog\Enum\CommentStatus;
use Marko\Blog\Events\Comment\CommentCreated;
use Marko\Blog\Repositories\CommentRepositoryInterface;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Blog\Services\CommentRateLimiterInterface;
use Marko\Blog\Services\CommentVerificationServiceInterface;
use Marko\Blog\Services\HoneypotValidatorInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Routing\Attributes\Post as PostRoute;
use Marko\Routing\Http\Response;

readonly class CommentController
{
    public function __construct(
        private PostRepositoryInterface $postRepository,
        private CommentRepositoryInterface $commentRepository,
        private HoneypotValidatorInterface $honeypotValidator,
        private CommentRateLimiterInterface $rateLimiter,
        private CommentVerificationServiceInterface $verificationService,
        private BlogConfigInterface $blogConfig,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[PostRoute('/blog/{slug}/comment')]
    public function submit(
        string $slug,
        string $name = '',
        string $email = '',
        string $content = '',
        string $honeypot = '',
        string $ipAddress = '',
        ?int $parentId = null,
        ?string $browserToken = null,
    ): Response {
        // Find the post by slug
        $post = $this->postRepository->findBySlug($slug);

        if ($post === null || !$post->isPublished()) {
            return Response::json(['error' => 'Post not found'], 404);
        }

        // Check honeypot - silently reject spam
        if (!$this->honeypotValidator->validate($honeypot)) {
            return Response::json(['status' => 'ok']);
        }

        // Check rate limit
        if (!$this->rateLimiter->isAllowed($ipAddress, $email)) {
            $secondsRemaining = $this->rateLimiter->getSecondsRemaining($ipAddress, $email);

            return Response::json([
                'error' => 'Rate limit exceeded',
                'retry_after' => $secondsRemaining,
            ], 429);
        }

        // Validate parent comment if provided
        if ($parentId !== null) {
            $parentValidationErrors = $this->validateParentComment($parentId, $post->id);

            if (!empty($parentValidationErrors)) {
                return Response::json(['errors' => $parentValidationErrors], 422);
            }
        }

        // Validate input
        $errors = $this->validateInput($name, $email, $content);

        if (!empty($errors)) {
            return Response::json(['errors' => $errors], 422);
        }

        // Create the comment
        $comment = new Comment();
        $comment->postId = $post->id;
        $comment->setPost($post);
        $comment->name = trim($name);
        $comment->email = trim($email);
        $comment->content = trim($content);
        $comment->parentId = $parentId;
        $comment->createdAt = date('Y-m-d H:i:s');

        // Check if auto-approve based on browser token
        if ($this->verificationService->shouldAutoApprove($email, $browserToken)) {
            $comment->status = CommentStatus::Verified;
            $comment->verifiedAt = date('Y-m-d H:i:s');
            $this->commentRepository->save($comment);

            // Dispatch event
            $this->eventDispatcher->dispatch(new CommentCreated($comment, $post));

            // Record submission for rate limiting
            $this->rateLimiter->recordSubmission($ipAddress, $email);

            return Response::json([
                'status' => 'success',
                'message' => 'Comment posted successfully',
            ], 201);
        }

        // Pending verification - save and send email
        $comment->status = CommentStatus::Pending;
        $this->commentRepository->save($comment);

        // Send verification email
        $this->verificationService->sendVerificationEmail($comment);

        // Dispatch event
        $this->eventDispatcher->dispatch(new CommentCreated($comment, $post));

        // Record submission for rate limiting
        $this->rateLimiter->recordSubmission($ipAddress, $email);

        return Response::json([
            'status' => 'pending',
            'message' => 'Please check your email to verify your comment',
        ], 202);
    }

    /**
     * Validate comment input data.
     *
     * @return array<string, string>
     */
    private function validateInput(
        string $name,
        string $email,
        string $content,
    ): array {
        $errors = [];

        if (trim($name) === '') {
            $errors['name'] = 'Name is required';
        }

        if (trim($email) === '') {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email must be a valid email address';
        }

        $trimmedContent = trim($content);
        if ($trimmedContent === '') {
            $errors['content'] = 'Comment content is required';
        } elseif (strlen($trimmedContent) < 10) {
            $errors['content'] = 'Comment content must be at least 10 characters';
        }

        return $errors;
    }

    /**
     * Validate parent comment for threaded replies.
     *
     * @return array<string, string>
     */
    private function validateParentComment(
        int $parentId,
        int $postId,
    ): array {
        $errors = [];

        $parentComment = $this->commentRepository->find($parentId);

        if ($parentComment === null) {
            $errors['parent_id'] = 'Parent comment not found';

            return $errors;
        }

        // Check parent comment belongs to the same post
        if ($parentComment->postId !== $postId) {
            $errors['parent_id'] = 'Parent comment does not belong to this post';

            return $errors;
        }

        // Check reply depth doesn't exceed max depth
        $parentDepth = $this->commentRepository->calculateDepth($parentId);
        $maxDepth = $this->blogConfig->getCommentMaxDepth();

        if ($parentDepth >= $maxDepth) {
            $errors['parent_id'] = 'Maximum reply depth exceeded';

            return $errors;
        }

        return $errors;
    }
}
