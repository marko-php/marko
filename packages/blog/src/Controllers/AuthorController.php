<?php

declare(strict_types=1);

namespace Marko\Blog\Controllers;

use Marko\Blog\Repositories\AuthorRepositoryInterface;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Blog\Services\PaginationServiceInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

class AuthorController
{
    public function __construct(
        private readonly AuthorRepositoryInterface $authorRepository,
        private readonly PostRepositoryInterface $postRepository,
        private readonly PaginationServiceInterface $paginationService,
        private readonly ViewInterface $view,
    ) {}

    #[Get('/blog/author/{slug}')]
    public function show(
        string $slug,
        int $page = 1,
    ): Response {
        $author = $this->authorRepository->findBySlug($slug);

        if ($author === null) {
            return new Response('Author not found', 404);
        }

        $perPage = $this->paginationService->getPerPage();
        $offset = $this->paginationService->calculateOffset($page);

        $posts = $this->postRepository->findPublishedByAuthor(
            $author->getId(),
            $perPage,
            $offset,
        );

        $totalPosts = $this->postRepository->countPublishedByAuthor($author->getId());

        $paginatedPosts = $this->paginationService->paginate(
            $posts,
            $totalPosts,
            $page,
        );

        return $this->view->render('blog::author/show', [
            'author' => $author,
            'posts' => $paginatedPosts,
            'breadcrumbs' => [['label' => $author->getName()]],
        ]);
    }
}
