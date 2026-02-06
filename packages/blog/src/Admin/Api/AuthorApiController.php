<?php

declare(strict_types=1);

namespace Marko\Blog\Admin\Api;

use Marko\AdminApi\ApiResponse;
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Blog\Entity\Author;
use Marko\Blog\Repositories\AuthorRepositoryInterface;
use Marko\Blog\Services\SlugGeneratorInterface;
use Marko\Routing\Attributes\Delete;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post as PostRoute;
use Marko\Routing\Attributes\Put;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

#[Middleware(AdminAuthMiddleware::class)]
class AuthorApiController
{
    public function __construct(
        private readonly AuthorRepositoryInterface $authorRepository,
        private readonly SlugGeneratorInterface $slugGenerator,
    ) {}

    #[Get('/admin/api/v1/blog/authors')]
    #[RequiresPermission('blog.authors.view')]
    public function index(
        Request $request,
    ): Response {
        $authors = $this->authorRepository->findAll();

        $data = array_map(
            static fn (Author $author): array => self::serializeAuthor($author),
            $authors,
        );

        return ApiResponse::success(data: $data);
    }

    #[Get('/admin/api/v1/blog/authors/{id}')]
    #[RequiresPermission('blog.authors.view')]
    public function show(
        int $id,
    ): Response {
        $author = $this->authorRepository->find($id);

        if ($author === null) {
            return ApiResponse::notFound('Author not found');
        }

        /** @var Author $author */
        return ApiResponse::success(data: self::serializeAuthor($author));
    }

    #[PostRoute('/admin/api/v1/blog/authors')]
    #[RequiresPermission('blog.authors.create')]
    public function store(
        Request $request,
    ): Response {
        $name = (string) $request->post('name', '');
        $email = (string) $request->post('email', '');
        $bio = $request->post('bio');

        $errors = $this->validateAuthorData($name, $email);

        if ($errors !== []) {
            return ApiResponse::error(
                errors: array_map(
                    static fn (string $message): array => ['message' => $message],
                    $errors,
                ),
                statusCode: 422,
            );
        }

        $author = new Author();
        $author->name = $name;
        $author->email = $email;
        $author->bio = $bio !== '' ? $bio : null;
        $author->slug = $this->slugGenerator->generate(
            $name,
            fn (string $slug): bool => $this->authorRepository->isSlugUnique($slug),
        );

        $this->authorRepository->save($author);

        return ApiResponse::created(data: self::serializeAuthor($author));
    }

    #[Put('/admin/api/v1/blog/authors/{id}')]
    #[RequiresPermission('blog.authors.edit')]
    public function update(
        int $id,
        Request $request,
    ): Response {
        $author = $this->authorRepository->find($id);

        if ($author === null) {
            return ApiResponse::notFound('Author not found');
        }

        $name = (string) $request->post('name', '');
        $email = (string) $request->post('email', '');
        $bio = $request->post('bio');

        $errors = $this->validateAuthorData($name, $email);

        if ($errors !== []) {
            return ApiResponse::error(
                errors: array_map(
                    static fn (string $message): array => ['message' => $message],
                    $errors,
                ),
                statusCode: 422,
            );
        }

        /** @var Author $author */
        $author->name = $name;
        $author->email = $email;
        $author->bio = $bio !== '' ? $bio : null;

        $this->authorRepository->save($author);

        return ApiResponse::success(data: self::serializeAuthor($author));
    }

    #[Delete('/admin/api/v1/blog/authors/{id}')]
    #[RequiresPermission('blog.authors.delete')]
    public function destroy(
        int $id,
    ): Response {
        $author = $this->authorRepository->find($id);

        if ($author === null) {
            return ApiResponse::notFound('Author not found');
        }

        $this->authorRepository->delete($author);

        return ApiResponse::success(data: ['deleted' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function serializeAuthor(
        Author $author,
    ): array {
        return [
            'id' => $author->id,
            'name' => $author->name,
            'email' => $author->email,
            'bio' => $author->bio,
            'slug' => $author->slug,
            'created_at' => $author->createdAt,
        ];
    }

    /**
     * @return array<string>
     */
    private function validateAuthorData(
        string $name,
        string $email,
    ): array {
        $errors = [];

        if ($name === '') {
            $errors[] = 'Name is required';
        }

        if ($email === '') {
            $errors[] = 'Email is required';
        }

        return $errors;
    }
}
