<?php

declare(strict_types=1);

namespace Marko\Blog\Admin\Api;

use DateTimeImmutable;
use Marko\AdminApi\ApiResponse;
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Blog\Entity\Comment;
use Marko\Blog\Enum\CommentStatus;
use Marko\Blog\Repositories\CommentRepositoryInterface;
use Marko\Routing\Attributes\Delete;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post as PostRoute;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

#[Middleware(AdminAuthMiddleware::class)]
class CommentApiController
{
    public function __construct(
        private readonly CommentRepositoryInterface $commentRepository,
    ) {}

    #[Get('/admin/api/v1/blog/comments')]
    #[RequiresPermission('blog.comments.view')]
    public function index(
        Request $request,
    ): Response {
        $comments = $this->commentRepository->findAll();

        $data = array_map(
            static fn (Comment $comment): array => self::serializeComment($comment),
            $comments,
        );

        return ApiResponse::success(data: $data);
    }

    #[Get('/admin/api/v1/blog/comments/{id}')]
    #[RequiresPermission('blog.comments.view')]
    public function show(
        int $id,
    ): Response {
        $comment = $this->commentRepository->find($id);

        if ($comment === null) {
            return ApiResponse::notFound('Comment not found');
        }

        return ApiResponse::success(data: self::serializeComment($comment));
    }

    #[PostRoute('/admin/api/v1/blog/comments/{id}/verify')]
    #[RequiresPermission('blog.comments.edit')]
    public function verify(
        int $id,
    ): Response {
        $comment = $this->commentRepository->find($id);

        if ($comment === null) {
            return ApiResponse::notFound('Comment not found');
        }

        $comment->status = CommentStatus::Verified;
        $comment->verifiedAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->commentRepository->save($comment);

        return ApiResponse::success(data: self::serializeComment($comment));
    }

    #[Delete('/admin/api/v1/blog/comments/{id}')]
    #[RequiresPermission('blog.comments.delete')]
    public function destroy(
        int $id,
    ): Response {
        $comment = $this->commentRepository->find($id);

        if ($comment === null) {
            return ApiResponse::notFound('Comment not found');
        }

        $this->commentRepository->delete($comment);

        return ApiResponse::success(data: ['deleted' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function serializeComment(
        Comment $comment,
    ): array {
        return [
            'id' => $comment->id,
            'post_id' => $comment->postId,
            'name' => $comment->name,
            'email' => $comment->email,
            'content' => $comment->content,
            'status' => $comment->status->value,
            'parent_id' => $comment->parentId,
            'verified_at' => $comment->verifiedAt,
            'created_at' => $comment->createdAt,
        ];
    }
}
