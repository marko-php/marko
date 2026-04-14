---
title: Build a REST API
description: Create a JSON API with Marko --- routing, validation, and authentication.
---

Build a RESTful API for managing articles, complete with authentication, validation, and proper HTTP responses.

## What You'll Build

- A full CRUD JSON API for articles
- Token-based authentication for protected endpoints
- Request validation with meaningful error responses
- Proper HTTP status codes (200, 201, 204, 404)

## Prerequisites

- PHP 8.5+
- Composer 2.x
- PostgreSQL (or MySQL)

## Step 1: Create a Minimal Project

```bash
composer create-project marko/skeleton my-api
cd my-api
composer require marko/core marko/routing marko/config marko/env \
    marko/database marko/database-pgsql marko/validation \
    marko/authentication marko/authentication-token
```

## Step 2: Define the Entity

Marko uses attribute-driven entities --- define your schema with `#[Table]` and `#[Column]` attributes, then `marko db:migrate` auto-generates migrations.

```php title="app/api/src/Entity/Article.php"
<?php

declare(strict_types=1);

namespace App\Api\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('articles')]
#[Index('idx_articles_author_email', ['author_email'])]
class Article extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(length: 200)]
    public string $title;

    #[Column(type: 'TEXT')]
    public string $body;

    #[Column]
    public string $authorEmail;

    #[Column]
    public ?string $createdAt = null;

    #[Column]
    public ?string $updatedAt = null;
}
```

Generate and run the migration:

```bash
marko db:migrate
```

## Step 3: Create the Repository

```php title="app/api/src/Repository/ArticleRepository.php"
<?php

declare(strict_types=1);

namespace App\Api\Repository;

use Marko\Database\Query\QueryBuilderInterface;

class ArticleRepository
{
    public function __construct(
        private readonly QueryBuilderInterface $queryBuilder,
    ) {}

    public function all(): array
    {
        return $this->queryBuilder->table('articles')
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function find(int $id): ?array
    {
        return $this->queryBuilder->table('articles')
            ->where('id', '=', $id)
            ->first();
    }

    public function create(array $data): int
    {
        return $this->queryBuilder->table('articles')->insert($data);
    }

    public function update(int $id, array $data): void
    {
        $this->queryBuilder->table('articles')
            ->where('id', '=', $id)
            ->update($data);
    }

    public function delete(int $id): void
    {
        $this->queryBuilder->table('articles')
            ->where('id', '=', $id)
            ->delete();
    }
}
```

## Step 4: Build the Controller

```php title="app/api/src/Controller/ArticleController.php"
<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Api\Repository\ArticleRepository;
use Marko\Authentication\AuthManager;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Routing\Attributes\Delete;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Attributes\Put;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Validation\Contracts\ValidatorInterface;
use DateTimeImmutable;

class ArticleController
{
    public function __construct(
        private readonly ArticleRepository $articleRepository,
        private readonly ValidatorInterface $validator,
        private readonly AuthManager $authManager,
    ) {}

    #[Get('/api/articles')]
    public function index(): Response
    {
        return Response::json(data: $this->articleRepository->all());
    }

    #[Get('/api/articles/{id}')]
    public function show(int $id): Response
    {
        $article = $this->articleRepository->find($id);

        if ($article === null) {
            return Response::json(
                data: ['error' => 'Article not found'],
                statusCode: 404,
            );
        }

        return Response::json(data: $article);
    }

    #[Post('/api/articles')]
    #[Middleware(AuthMiddleware::class)]
    public function store(Request $request): Response
    {
        $data = json_decode($request->body(), true, flags: JSON_THROW_ON_ERROR);

        $errors = $this->validator->validate($data, [
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'body' => ['required', 'string'],
        ]);

        if ($errors->isNotEmpty()) {
            return Response::json(
                data: ['errors' => $errors->all()],
                statusCode: 422,
            );
        }

        $user = $this->authManager->user();

        $id = $this->articleRepository->create([
            'title' => $data['title'],
            'body' => $data['body'],
            'author_email' => $user?->getAuthIdentifier(),
            'created_at' => new DateTimeImmutable(),
            'updated_at' => new DateTimeImmutable(),
        ]);

        return Response::json(
            data: $this->articleRepository->find($id),
            statusCode: 201,
        );
    }

    #[Put('/api/articles/{id}')]
    #[Middleware(AuthMiddleware::class)]
    public function update(int $id, Request $request): Response
    {
        $data = json_decode($request->body(), true, flags: JSON_THROW_ON_ERROR);

        $errors = $this->validator->validate($data, [
            'title' => ['string', 'min:3', 'max:200'],
            'body' => ['string'],
        ]);

        if ($errors->isNotEmpty()) {
            return Response::json(
                data: ['errors' => $errors->all()],
                statusCode: 422,
            );
        }

        $this->articleRepository->update($id, [
            ...$data,
            'updated_at' => new DateTimeImmutable(),
        ]);

        return Response::json(data: $this->articleRepository->find($id));
    }

    #[Delete('/api/articles/{id}')]
    #[Middleware(AuthMiddleware::class)]
    public function destroy(int $id): Response
    {
        $this->articleRepository->delete($id);

        return Response::json(data: null, statusCode: 204);
    }
}
```

## Step 5: Start the Server

```bash
marko up
```

## Step 6: Test with cURL

```bash
# List articles
curl http://localhost:8000/api/articles

# Get one article
curl http://localhost:8000/api/articles/1

# Create (with auth token)
curl -X POST http://localhost:8000/api/articles \
    -H "Authorization: Bearer YOUR_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"title": "My First Article", "body": "Hello from Marko!"}'

# Delete
curl -X DELETE http://localhost:8000/api/articles/1 \
    -H "Authorization: Bearer YOUR_TOKEN"
```

## What You've Learned

- Minimal Marko installation for APIs (no views, no sessions)
- Entity-driven database schemas with `#[Table]` and `#[Column]` attributes
- RESTful controller with full CRUD
- Request validation with [`ValidatorInterface`](/docs/packages/validation/)
- Token-based authentication with [`AuthMiddleware`](/docs/packages/authentication/)
- Proper HTTP status codes using [`Response::json()`](/docs/packages/routing/)

## Next Steps

- [Build a Blog](/docs/tutorials/build-a-blog/) --- build a full blog application
- [Create a Custom Module](/docs/tutorials/custom-module/) --- build a reusable Composer package
