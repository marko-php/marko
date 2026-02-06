<?php

declare(strict_types=1);

namespace Marko\Blog\Admin\Api;

use Marko\AdminApi\ApiResponse;
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Blog\Entity\Category;
use Marko\Blog\Repositories\CategoryRepositoryInterface;
use Marko\Blog\Services\SlugGeneratorInterface;
use Marko\Routing\Attributes\Delete;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post as PostRoute;
use Marko\Routing\Attributes\Put;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

#[Middleware(AdminAuthMiddleware::class)]
class CategoryApiController
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly SlugGeneratorInterface $slugGenerator,
    ) {}

    #[Get('/admin/api/v1/blog/categories')]
    #[RequiresPermission('blog.categories.view')]
    public function index(
        Request $request,
    ): Response {
        $categories = $this->categoryRepository->findAll();

        $data = array_map(
            static fn (Category $category): array => self::serializeCategory($category),
            $categories,
        );

        return ApiResponse::success(data: $data);
    }

    #[Get('/admin/api/v1/blog/categories/{id}')]
    #[RequiresPermission('blog.categories.view')]
    public function show(
        int $id,
    ): Response {
        $category = $this->categoryRepository->find($id);

        if ($category === null) {
            return ApiResponse::notFound('Category not found');
        }

        /** @var Category $category */
        return ApiResponse::success(data: self::serializeCategory($category));
    }

    #[PostRoute('/admin/api/v1/blog/categories')]
    #[RequiresPermission('blog.categories.create')]
    public function store(
        Request $request,
    ): Response {
        $name = (string) $request->post('name', '');
        $parentId = $request->post('parent_id', '');

        $errors = $this->validateCategoryData($name);

        if ($errors !== []) {
            return ApiResponse::error(
                errors: array_map(
                    static fn (string $message): array => ['message' => $message],
                    $errors,
                ),
                statusCode: 422,
            );
        }

        $category = new Category();
        $category->name = $name;
        $category->parentId = $parentId !== '' ? (int) $parentId : null;
        $category->slug = $this->slugGenerator->generate(
            $name,
            fn (string $slug): bool => $this->categoryRepository->isSlugUnique($slug),
        );

        $this->categoryRepository->save($category);

        return ApiResponse::created(data: self::serializeCategory($category));
    }

    #[Put('/admin/api/v1/blog/categories/{id}')]
    #[RequiresPermission('blog.categories.edit')]
    public function update(
        int $id,
        Request $request,
    ): Response {
        $category = $this->categoryRepository->find($id);

        if ($category === null) {
            return ApiResponse::notFound('Category not found');
        }

        $name = (string) $request->post('name', '');
        $parentId = $request->post('parent_id', '');

        $errors = $this->validateCategoryData($name);

        if ($errors !== []) {
            return ApiResponse::error(
                errors: array_map(
                    static fn (string $message): array => ['message' => $message],
                    $errors,
                ),
                statusCode: 422,
            );
        }

        /** @var Category $category */
        $category->name = $name;
        $category->parentId = $parentId !== '' ? (int) $parentId : null;

        $this->categoryRepository->save($category);

        return ApiResponse::success(data: self::serializeCategory($category));
    }

    #[Delete('/admin/api/v1/blog/categories/{id}')]
    #[RequiresPermission('blog.categories.delete')]
    public function destroy(
        int $id,
    ): Response {
        $category = $this->categoryRepository->find($id);

        if ($category === null) {
            return ApiResponse::notFound('Category not found');
        }

        $this->categoryRepository->delete($category);

        return ApiResponse::success(data: ['deleted' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function serializeCategory(
        Category $category,
    ): array {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'parent_id' => $category->parentId,
            'created_at' => $category->createdAt,
        ];
    }

    /**
     * @return array<string>
     */
    private function validateCategoryData(
        string $name,
    ): array {
        $errors = [];

        if ($name === '') {
            $errors[] = 'Name is required';
        }

        return $errors;
    }
}
