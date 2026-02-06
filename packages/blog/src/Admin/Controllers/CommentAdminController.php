<?php

declare(strict_types=1);

namespace Marko\Blog\Admin\Controllers;

use DateTimeImmutable;
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Blog\Entity\Comment;
use Marko\Blog\Enum\CommentStatus;
use Marko\Blog\Events\Comment\CommentDeleted;
use Marko\Blog\Events\Comment\CommentVerified;
use Marko\Blog\Repositories\CommentRepositoryInterface;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Blog\Services\PaginationServiceInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Routing\Attributes\Delete;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post as PostRoute;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

#[Middleware(AdminAuthMiddleware::class)]
class CommentAdminController
{
    public function __construct(
        private readonly CommentRepositoryInterface $commentRepository,
        private readonly PostRepositoryInterface $postRepository,
        private readonly PaginationServiceInterface $paginationService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ViewInterface $view,
    ) {}

    #[Get('/admin/blog/comments')]
    #[RequiresPermission('blog.comments.view')]
    public function index(
        Request $request,
    ): Response {
        $page = (int) ($request->query('page', '1'));

        if ($page < 1) {
            $page = 1;
        }

        $perPage = $this->paginationService->getPerPage();
        $allComments = $this->commentRepository->findAll();
        $totalComments = count($allComments);

        $offset = $this->paginationService->calculateOffset($page);
        $comments = array_slice($allComments, $offset, $perPage);

        $pagination = $this->paginationService->paginate($comments, $totalComments, $page);

        return $this->view->render('blog::admin/comment/index', [
            'comments' => $pagination,
        ]);
    }

    #[Get('/admin/blog/comments/{id}')]
    #[RequiresPermission('blog.comments.view')]
    public function show(
        int $id,
    ): Response {
        $comment = $this->commentRepository->find($id);

        if ($comment === null) {
            return new Response('Comment not found', 404);
        }

        return $this->view->render('blog::admin/comment/show', [
            'comment' => $comment,
        ]);
    }

    #[PostRoute('/admin/blog/comments/{id}/verify')]
    #[RequiresPermission('blog.comments.verify')]
    public function verify(
        int $id,
    ): Response {
        $comment = $this->commentRepository->find($id);

        if ($comment === null) {
            return new Response('Comment not found', 404);
        }

        /** @var Comment $comment */
        $comment->status = CommentStatus::Verified;
        $comment->verifiedAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->commentRepository->save($comment);

        $post = $this->postRepository->find($comment->postId);

        $this->eventDispatcher->dispatch(new CommentVerified(
            comment: $comment,
            post: $post,
            verificationMethod: 'admin',
        ));

        return Response::redirect('/admin/blog/comments');
    }

    #[Delete('/admin/blog/comments/{id}')]
    #[RequiresPermission('blog.comments.delete')]
    public function destroy(
        int $id,
    ): Response {
        $comment = $this->commentRepository->find($id);

        if ($comment === null) {
            return new Response('Comment not found', 404);
        }

        $post = $this->postRepository->find($comment->postId);

        $this->commentRepository->delete($comment);

        $this->eventDispatcher->dispatch(new CommentDeleted(
            comment: $comment,
            post: $post,
        ));

        return Response::redirect('/admin/blog/comments');
    }
}
