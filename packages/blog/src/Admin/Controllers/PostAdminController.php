<?php

declare(strict_types=1);

namespace Marko\Blog\Admin\Controllers;

use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Blog\Entity\Post;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Events\Post\PostCreated;
use Marko\Blog\Events\Post\PostUpdated;
use Marko\Blog\Repositories\AuthorRepositoryInterface;
use Marko\Blog\Repositories\CategoryRepositoryInterface;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Blog\Repositories\TagRepositoryInterface;
use Marko\Blog\Services\PaginationServiceInterface;
use Marko\Blog\Services\SlugGeneratorInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Routing\Attributes\Delete;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post as PostRoute;
use Marko\Routing\Attributes\Put;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

#[Middleware(AdminAuthMiddleware::class)]
class PostAdminController
{
    public function __construct(
        private readonly PostRepositoryInterface $postRepository,
        private readonly AuthorRepositoryInterface $authorRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly TagRepositoryInterface $tagRepository,
        private readonly PaginationServiceInterface $paginationService,
        private readonly SlugGeneratorInterface $slugGenerator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ViewInterface $view,
    ) {}

    #[Get('/admin/blog/posts')]
    #[RequiresPermission('blog.posts.view')]
    public function index(
        Request $request,
    ): Response {
        $page = (int) ($request->query('page', '1'));

        if ($page < 1) {
            $page = 1;
        }

        $perPage = $this->paginationService->getPerPage();
        $allPosts = $this->postRepository->findAll();
        $totalPosts = count($allPosts);

        $offset = $this->paginationService->calculateOffset($page);
        $posts = array_slice($allPosts, $offset, $perPage);

        $pagination = $this->paginationService->paginate($posts, $totalPosts, $page);

        return $this->view->render('blog::admin/post/index', [
            'posts' => $pagination,
        ]);
    }

    #[Get('/admin/blog/posts/create')]
    #[RequiresPermission('blog.posts.create')]
    public function create(): Response
    {
        return $this->view->render('blog::admin/post/create', [
            'authors' => $this->authorRepository->findAll(),
            'categories' => $this->categoryRepository->findAll(),
            'tags' => $this->tagRepository->findAll(),
        ]);
    }

    #[PostRoute('/admin/blog/posts')]
    #[RequiresPermission('blog.posts.create')]
    public function store(
        Request $request,
    ): Response {
        $title = (string) $request->post('title', '');
        $content = (string) $request->post('content', '');
        $summary = $request->post('summary');
        $authorId = (int) $request->post('author_id', '0');
        $categoryIds = (array) ($request->post('category_ids') ?? []);
        $tagIds = (array) ($request->post('tag_ids') ?? []);

        // Validate required fields
        $errors = $this->validatePostData($title, $content, $authorId);

        if ($errors !== []) {
            return $this->view->render('blog::admin/post/create', [
                'errors' => $errors,
                'input' => $request->post(),
                'authors' => $this->authorRepository->findAll(),
                'categories' => $this->categoryRepository->findAll(),
                'tags' => $this->tagRepository->findAll(),
            ]);
        }

        $post = new Post(
            title: $title,
            content: $content,
            authorId: $authorId,
            slugGenerator: $this->slugGenerator,
            summary: $summary !== '' ? $summary : null,
            uniquenessChecker: fn (string $slug): bool => $this->postRepository->isSlugUnique($slug),
        );

        $this->postRepository->save($post);

        $this->postRepository->syncCategories($post->id, $categoryIds);
        $this->postRepository->syncTags($post->id, $tagIds);

        $this->eventDispatcher->dispatch(new PostCreated(post: $post));

        return Response::redirect('/admin/blog/posts/' . $post->id . '/edit');
    }

    #[Get('/admin/blog/posts/{id}/edit')]
    #[RequiresPermission('blog.posts.edit')]
    public function edit(
        int $id,
    ): Response {
        $post = $this->postRepository->find($id);

        if ($post === null) {
            return new Response('Post not found', 404);
        }

        $postCategories = $this->postRepository->getCategoriesForPost($id);
        $postTags = $this->postRepository->getTagsForPost($id);

        return $this->view->render('blog::admin/post/edit', [
            'post' => $post,
            'authors' => $this->authorRepository->findAll(),
            'categories' => $this->categoryRepository->findAll(),
            'tags' => $this->tagRepository->findAll(),
            'postCategories' => $postCategories,
            'postTags' => $postTags,
        ]);
    }

    #[Put('/admin/blog/posts/{id}')]
    #[RequiresPermission('blog.posts.edit')]
    public function update(
        int $id,
        Request $request,
    ): Response {
        $post = $this->postRepository->find($id);

        if ($post === null) {
            return new Response('Post not found', 404);
        }

        $title = (string) $request->post('title', '');
        $content = (string) $request->post('content', '');
        $summary = $request->post('summary');
        $authorId = (int) $request->post('author_id', '0');
        $categoryIds = (array) ($request->post('category_ids') ?? []);
        $tagIds = (array) ($request->post('tag_ids') ?? []);

        $errors = $this->validatePostData($title, $content, $authorId);

        if ($errors !== []) {
            $postCategories = $this->postRepository->getCategoriesForPost($id);
            $postTags = $this->postRepository->getTagsForPost($id);

            return $this->view->render('blog::admin/post/edit', [
                'errors' => $errors,
                'post' => $post,
                'input' => $request->post(),
                'authors' => $this->authorRepository->findAll(),
                'categories' => $this->categoryRepository->findAll(),
                'tags' => $this->tagRepository->findAll(),
                'postCategories' => $postCategories,
                'postTags' => $postTags,
            ]);
        }

        /** @var Post $post */
        $post->title = $title;
        $post->content = $content;
        $post->summary = $summary !== '' ? $summary : null;
        $post->authorId = $authorId;

        $this->postRepository->save($post);

        $this->postRepository->syncCategories($post->id, $categoryIds);
        $this->postRepository->syncTags($post->id, $tagIds);

        $this->eventDispatcher->dispatch(new PostUpdated(post: $post));

        return Response::redirect('/admin/blog/posts/' . $post->id . '/edit');
    }

    #[Delete('/admin/blog/posts/{id}')]
    #[RequiresPermission('blog.posts.delete')]
    public function destroy(
        int $id,
    ): Response {
        $post = $this->postRepository->find($id);

        if ($post === null) {
            return new Response('Post not found', 404);
        }

        $this->postRepository->delete($post);

        return Response::redirect('/admin/blog/posts');
    }

    #[PostRoute('/admin/blog/posts/{id}/publish')]
    #[RequiresPermission('blog.posts.publish')]
    public function publish(
        int $id,
    ): Response {
        $post = $this->postRepository->find($id);

        if ($post === null) {
            return new Response('Post not found', 404);
        }

        /** @var Post $post */
        $post->setStatus(PostStatus::Published);

        $this->postRepository->save($post);

        return Response::redirect('/admin/blog/posts/' . $post->id . '/edit');
    }

    /**
     * @return array<string>
     */
    private function validatePostData(
        string $title,
        string $content,
        int $authorId,
    ): array {
        $errors = [];

        if ($title === '') {
            $errors[] = 'Title is required';
        }

        if ($content === '') {
            $errors[] = 'Content is required';
        }

        if ($authorId === 0) {
            $errors[] = 'Author is required';
        }

        return $errors;
    }
}
