<?php

declare(strict_types=1);

namespace Marko\Blog\Admin\Api;

use Marko\AdminApi\ApiResponse;
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Blog\Entity\Tag;
use Marko\Blog\Repositories\TagRepositoryInterface;
use Marko\Blog\Services\SlugGeneratorInterface;
use Marko\Routing\Attributes\Delete;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post as PostRoute;
use Marko\Routing\Attributes\Put;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

#[Middleware(AdminAuthMiddleware::class)]
class TagApiController
{
    public function __construct(
        private readonly TagRepositoryInterface $tagRepository,
        private readonly SlugGeneratorInterface $slugGenerator,
    ) {}

    #[Get('/admin/api/v1/blog/tags')]
    #[RequiresPermission('blog.tags.view')]
    public function index(
        Request $request,
    ): Response {
        $tags = $this->tagRepository->findAll();

        $data = array_map(
            static fn (Tag $tag): array => self::serializeTag($tag),
            $tags,
        );

        return ApiResponse::success(data: $data);
    }

    #[Get('/admin/api/v1/blog/tags/{id}')]
    #[RequiresPermission('blog.tags.view')]
    public function show(
        int $id,
    ): Response {
        $tag = $this->tagRepository->find($id);

        if ($tag === null) {
            return ApiResponse::notFound('Tag not found');
        }

        /** @var Tag $tag */
        return ApiResponse::success(data: self::serializeTag($tag));
    }

    #[PostRoute('/admin/api/v1/blog/tags')]
    #[RequiresPermission('blog.tags.create')]
    public function store(
        Request $request,
    ): Response {
        $name = (string) $request->post('name', '');

        $errors = $this->validateTagData($name);

        if ($errors !== []) {
            return ApiResponse::error(
                errors: array_map(
                    static fn (string $message): array => ['message' => $message],
                    $errors,
                ),
                statusCode: 422,
            );
        }

        $tag = new Tag();
        $tag->name = $name;
        $tag->slug = $this->slugGenerator->generate(
            $name,
            fn (string $slug): bool => $this->tagRepository->isSlugUnique($slug),
        );

        $this->tagRepository->save($tag);

        return ApiResponse::created(data: self::serializeTag($tag));
    }

    #[Put('/admin/api/v1/blog/tags/{id}')]
    #[RequiresPermission('blog.tags.edit')]
    public function update(
        int $id,
        Request $request,
    ): Response {
        $tag = $this->tagRepository->find($id);

        if ($tag === null) {
            return ApiResponse::notFound('Tag not found');
        }

        $name = (string) $request->post('name', '');

        $errors = $this->validateTagData($name);

        if ($errors !== []) {
            return ApiResponse::error(
                errors: array_map(
                    static fn (string $message): array => ['message' => $message],
                    $errors,
                ),
                statusCode: 422,
            );
        }

        /** @var Tag $tag */
        $tag->name = $name;

        $this->tagRepository->save($tag);

        return ApiResponse::success(data: self::serializeTag($tag));
    }

    #[Delete('/admin/api/v1/blog/tags/{id}')]
    #[RequiresPermission('blog.tags.delete')]
    public function destroy(
        int $id,
    ): Response {
        $tag = $this->tagRepository->find($id);

        if ($tag === null) {
            return ApiResponse::notFound('Tag not found');
        }

        $this->tagRepository->delete($tag);

        return ApiResponse::success(data: ['deleted' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function serializeTag(
        Tag $tag,
    ): array {
        return [
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'created_at' => $tag->createdAt,
        ];
    }

    /**
     * @return array<string>
     */
    private function validateTagData(
        string $name,
    ): array {
        $errors = [];

        if ($name === '') {
            $errors[] = 'Name is required';
        }

        return $errors;
    }
}
