<?php

declare(strict_types=1);

namespace Marko\Blog\Controllers;

use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Blog\Services\PaginationServiceInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

class PostController
{
    public function __construct(
        private readonly PostRepositoryInterface $repository,
        private readonly PaginationServiceInterface $paginationService,
        private readonly ViewInterface $view,
    ) {}

    #[Get('/blog')]
    public function index(
        int $page = 1,
    ): Response {
        // Validate page number is positive
        if ($page < 1) {
            return new Response('Page not found', 404);
        }

        $perPage = $this->paginationService->getPerPage();
        $totalPosts = $this->repository->countPublished();
        $totalPages = $totalPosts > 0 ? (int) ceil($totalPosts / $perPage) : 0;

        // Validate page doesn't exceed total pages (allow page 1 even when no posts)
        if ($totalPages > 0 && $page > $totalPages) {
            return new Response('Page not found', 404);
        }

        $offset = $this->paginationService->calculateOffset($page);
        $posts = $this->repository->findPublishedPaginated($perPage, $offset);

        $pagination = $this->paginationService->paginate($posts, $totalPosts, $page);

        return $this->view->render('blog::post/index', [
            'posts' => $pagination,
        ]);
    }

    #[Get('/blog/{slug}')]
    public function show(
        string $slug,
    ): Response {
        $post = $this->repository->findBySlug($slug);

        if ($post === null) {
            return new Response('Post not found', 404);
        }

        return $this->view->render('blog::post/show', [
            'post' => $post,
        ]);
    }
}
