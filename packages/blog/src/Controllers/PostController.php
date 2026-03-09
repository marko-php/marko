<?php

declare(strict_types=1);

namespace Marko\Blog\Controllers;

use Marko\Blog\Entity\Post;
use Marko\Blog\Repositories\AuthorRepositoryInterface;
use Marko\Blog\Repositories\CategoryRepositoryInterface;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Blog\Services\CommentThreadingServiceInterface;
use Marko\Blog\Services\PaginationServiceInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Marko\Session\Contracts\SessionInterface;
use Marko\View\ViewInterface;

readonly class PostController
{
    public function __construct(
        private PostRepositoryInterface $repository,
        private AuthorRepositoryInterface $authorRepository,
        private CategoryRepositoryInterface $categoryRepository,
        private PaginationServiceInterface $paginationService,
        private ViewInterface $view,
        private SessionInterface $session,
        private CommentThreadingServiceInterface $commentThreadingService,
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

        // Load relationships for each post
        $this->loadPostRelationships($posts);

        $pagination = $this->paginationService->paginate($posts, $totalPosts, $page);

        return $this->view->render('blog::post/index', [
            'posts' => $pagination,
        ]);
    }

    /**
     * Load author, categories, and tags for each post.
     *
     * @param array<Post> $posts
     */
    private function loadPostRelationships(
        array $posts,
    ): void {
        foreach ($posts as $post) {
            // Load author
            $author = $this->authorRepository->find($post->getAuthorId());
            if ($author !== null) {
                $post->setAuthor($author);
            }

            // Load categories
            $categories = $this->repository->getCategoriesForPost($post->getId());
            $post->setCategories($categories);

            // Load tags
            $tags = $this->repository->getTagsForPost($post->getId());
            $post->setTags($tags);
        }
    }

    #[Get('/blog/{slug}')]
    public function show(
        string $slug,
    ): Response {
        $post = $this->repository->findBySlug($slug);

        if ($post === null || !$post->isPublished()) {
            return new Response('Post not found', 404);
        }

        // Load author
        $author = $this->authorRepository->find($post->getAuthorId());
        if ($author !== null) {
            $post->setAuthor($author);
        }

        $categories = $this->repository->getCategoriesForPost($post->getId());
        $tags = $this->repository->getTagsForPost($post->getId());
        $comments = $this->commentThreadingService->getThreadedComments($post->getId());

        // Build category paths for breadcrumb display
        $categoryPaths = [];
        foreach ($categories as $category) {
            $categoryPaths[$category->id] = $this->categoryRepository->getPath($category);
        }

        // Set relationships on post
        $post->setCategories($categories);
        $post->setTags($tags);

        $this->session->start();
        $successMessages = $this->session->flash()->get('success');
        $this->session->save();

        return $this->view->render('blog::post/show', [
            'post' => $post,
            'categories' => $categories,
            'categoryPaths' => $categoryPaths,
            'tags' => $tags,
            'comments' => $comments,
            'verificationSuccess' => !empty($successMessages),
        ]);
    }
}
