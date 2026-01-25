<?php

declare(strict_types=1);

namespace Marko\Blog\Controllers;

use Marko\Blog\Repositories\CategoryRepositoryInterface;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Blog\Services\PaginationServiceInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

class CategoryController
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly PostRepositoryInterface $postRepository,
        private readonly PaginationServiceInterface $paginationService,
        private readonly ViewInterface $view,
    ) {}

    #[Get('/blog/category/{slug}')]
    public function show(
        string $slug,
        int $page = 1,
    ): Response {
        $category = $this->categoryRepository->findBySlug($slug);

        if ($category === null) {
            return new Response('Category not found', 404);
        }

        $perPage = $this->paginationService->getPerPage();
        $offset = $this->paginationService->calculateOffset($page);
        $totalPosts = $this->postRepository->countPublishedByCategory($category->id);
        $posts = $this->postRepository->findPublishedByCategory($category->id, $perPage, $offset);
        $path = $this->categoryRepository->getPath($category);

        $pagination = $this->paginationService->paginate($posts, $totalPosts, $page);

        return $this->view->render('blog::category/show', [
            'category' => $category,
            'path' => $path,
            'posts' => $pagination,
        ]);
    }
}
