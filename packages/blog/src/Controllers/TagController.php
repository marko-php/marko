<?php

declare(strict_types=1);

namespace Marko\Blog\Controllers;

use Marko\Blog\Entity\Post;
use Marko\Blog\Repositories\AuthorRepositoryInterface;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Blog\Repositories\TagRepositoryInterface;
use Marko\Blog\Services\PaginationServiceInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

class TagController
{
    public function __construct(
        private readonly TagRepositoryInterface $tagRepository,
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

    #[Get('/blog/tag/{slug}')]
    public function index(
        string $slug,
        int $page = 1,
    ): Response {
        $tag = $this->tagRepository->findBySlug($slug);

        if ($tag === null) {
            return new Response('Tag not found', 404);
        }

        $perPage = $this->paginationService->getPerPage();
        $offset = $this->paginationService->calculateOffset($page);
        $totalItems = $this->postRepository->countPublishedByTag($tag->id);
        $posts = $this->postRepository->findPublishedByTag($tag->id, $perPage, $offset);

        $this->loadPostRelationships($posts);

        $paginatedResult = $this->paginationService->paginate(
            items: $posts,
            totalItems: $totalItems,
            currentPage: $page,
        );

        return $this->view->render('blog::tag/index', [
            'tag' => $tag,
            'posts' => $paginatedResult,
        ]);
    }
}
