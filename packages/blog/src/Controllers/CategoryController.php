<?php

declare(strict_types=1);

namespace Marko\Blog\Controllers;

use Marko\Blog\Entity\Post;
use Marko\Blog\Repositories\AuthorRepositoryInterface;
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
        private readonly AuthorRepositoryInterface $authorRepository,
        private readonly PaginationServiceInterface $paginationService,
        private readonly ViewInterface $view,
    ) {}

    /**
     * Load author, categories, and tags for each post.
     *
     * @param array<Post> $posts
     */
    private function loadPostRelationships(
        array $posts,
    ): void {
        foreach ($posts as $post) {
            $author = $this->authorRepository->find($post->getAuthorId());
            if ($author !== null) {
                $post->setAuthor($author);
            }

            $categories = $this->postRepository->getCategoriesForPost($post->getId());
            $post->setCategories($categories);

            $tags = $this->postRepository->getTagsForPost($post->getId());
            $post->setTags($tags);
        }
    }

    #[Get('/blog/category/{slug}')]
    public function show(
        string $slug,
        int $page = 1,
    ): Response {
        $category = $this->categoryRepository->findBySlug($slug);

        if ($category === null) {
            return new Response('Category not found', 404);
        }

        // Include posts from this category and all descendant categories
        $descendantIds = $this->categoryRepository->getDescendantIds($category->id);
        $categoryIds = [$category->id, ...$descendantIds];

        $perPage = $this->paginationService->getPerPage();
        $offset = $this->paginationService->calculateOffset($page);
        $totalPosts = $this->postRepository->countPublishedByCategories($categoryIds);
        $posts = $this->postRepository->findPublishedByCategories($categoryIds, $perPage, $offset);
        $path = $this->categoryRepository->getPath($category);

        $this->loadPostRelationships($posts);

        $pagination = $this->paginationService->paginate($posts, $totalPosts, $page);

        $breadcrumbs = array_map(fn ($cat) => [
            'label' => $cat->name,
            'url' => $cat->id === $category->id ? null : "/blog/category/$cat->slug",
        ], $path);

        return $this->view->render('blog::category/show', [
            'category' => $category,
            'posts' => $pagination,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }
}
