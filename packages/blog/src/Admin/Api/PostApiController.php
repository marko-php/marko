<?php

declare(strict_types=1);

namespace Marko\Blog\Admin\Api;

use Marko\AdminApi\ApiResponse;
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Blog\Entity\Post;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Blog\Services\PaginationServiceInterface;
use Marko\Blog\Services\SlugGeneratorInterface;
use Marko\Routing\Attributes\Delete;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post as PostRoute;
use Marko\Routing\Attributes\Put;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

#[Middleware(AdminAuthMiddleware::class)]
class PostApiController
{
    public function __construct(
        private readonly PostRepositoryInterface $postRepository,
        private readonly PaginationServiceInterface $paginationService,
        private readonly SlugGeneratorInterface $slugGenerator,
    ) {}

    #[Get('/admin/api/v1/blog/posts')]
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

        $data = array_map(
            static fn (Post $post): array => self::serializePost($post),
            $posts,
        );

        return ApiResponse::paginated(
            data: $data,
            page: $page,
            perPage: $perPage,
            total: $totalPosts,
        );
    }

    #[Get('/admin/api/v1/blog/posts/{id}')]
    #[RequiresPermission('blog.posts.view')]
    public function show(
        int $id,
    ): Response {
        $post = $this->postRepository->find($id);

        if ($post === null) {
            return ApiResponse::notFound('Post not found');
        }

        /** @var Post $post */
        return ApiResponse::success(data: self::serializePost($post));
    }

    #[PostRoute('/admin/api/v1/blog/posts')]
    #[RequiresPermission('blog.posts.create')]
    public function store(
        Request $request,
    ): Response {
        $title = (string) $request->post('title', '');
        $content = (string) $request->post('content', '');
        $summary = $request->post('summary');
        $authorId = (int) $request->post('author_id', '0');

        $errors = $this->validatePostData($title, $content, $authorId);

        if ($errors !== []) {
            return ApiResponse::error(
                errors: array_map(
                    static fn (string $message): array => ['message' => $message],
                    $errors,
                ),
                statusCode: 422,
            );
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

        return ApiResponse::created(data: self::serializePost($post));
    }

    #[Put('/admin/api/v1/blog/posts/{id}')]
    #[RequiresPermission('blog.posts.edit')]
    public function update(
        int $id,
        Request $request,
    ): Response {
        $post = $this->postRepository->find($id);

        if ($post === null) {
            return ApiResponse::notFound('Post not found');
        }

        $title = (string) $request->post('title', '');
        $content = (string) $request->post('content', '');
        $summary = $request->post('summary');
        $authorId = (int) $request->post('author_id', '0');

        $errors = $this->validatePostData($title, $content, $authorId);

        if ($errors !== []) {
            return ApiResponse::error(
                errors: array_map(
                    static fn (string $message): array => ['message' => $message],
                    $errors,
                ),
                statusCode: 422,
            );
        }

        /** @var Post $post */
        $post->title = $title;
        $post->content = $content;
        $post->summary = $summary !== '' ? $summary : null;
        $post->authorId = $authorId;

        $this->postRepository->save($post);

        return ApiResponse::success(data: self::serializePost($post));
    }

    #[Delete('/admin/api/v1/blog/posts/{id}')]
    #[RequiresPermission('blog.posts.delete')]
    public function destroy(
        int $id,
    ): Response {
        $post = $this->postRepository->find($id);

        if ($post === null) {
            return ApiResponse::notFound('Post not found');
        }

        $this->postRepository->delete($post);

        return ApiResponse::success(data: ['deleted' => true]);
    }

    #[PostRoute('/admin/api/v1/blog/posts/{id}/publish')]
    #[RequiresPermission('blog.posts.publish')]
    public function publish(
        int $id,
    ): Response {
        $post = $this->postRepository->find($id);

        if ($post === null) {
            return ApiResponse::notFound('Post not found');
        }

        /** @var Post $post */
        $post->setStatus(PostStatus::Published);

        $this->postRepository->save($post);

        return ApiResponse::success(data: self::serializePost($post));
    }

    /**
     * @return array<string, mixed>
     */
    private static function serializePost(
        Post $post,
    ): array {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'status' => $post->status->value,
            'summary' => $post->summary,
            'author_id' => $post->authorId,
            'created_at' => $post->createdAt,
        ];
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
