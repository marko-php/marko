---
title: Build a REST API
description: Create a JSON API with Marko — routing, validation, and authentication.
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

```php title="app/api/src/Entity/Article.php"
<?php

declare(strict_types=1);

namespace App\Api\Entity;

use Marko\Database\Attribute\Entity;
use Marko\Database\Attribute\Id;
use DateTimeImmutable;

#[Entity(table: 'articles')]
class Article
{
    #[Id]
    public int $id;

    public string $title;

    public string $body;

    public string $authorEmail;

    public DateTimeImmutable $createdAt;

    public DateTimeImmutable $updatedAt;
}
```

## Step 3: Create the Repository

```php title="app/api/src/Repository/ArticleRepository.php"
<?php

declare(strict_types=1);

namespace App\Api\Repository;

use Marko\Database\ConnectionInterface;

class ArticleRepository
{
    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {}

    public function all(): array
    {
        return $this->connection->table('articles')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function find(int $id): ?array
    {
        return $this->connection->table('articles')
            ->where('id', $id)
            ->first();
    }

    public function create(array $data): int
    {
        return $this->connection->table('articles')->insert($data);
    }

    public function update(int $id, array $data): void
    {
        $this->connection->table('articles')
            ->where('id', $id)
            ->update($data);
    }

    public function delete(int $id): void
    {
        $this->connection->table('articles')
            ->where('id', $id)
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
use Marko\Http\JsonResponse;
use Marko\Http\RequestInterface;
use Marko\Http\ResponseInterface;
use Marko\Routing\Attribute\Delete;
use Marko\Routing\Attribute\Get;
use Marko\Routing\Attribute\Middleware;
use Marko\Routing\Attribute\Post;
use Marko\Routing\Attribute\Put;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Validation\ValidatorInterface;
use DateTimeImmutable;

class ArticleController
{
    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Get('/api/articles')]
    public function index(): ResponseInterface
    {
        return new JsonResponse(data: $this->articles->all());
    }

    #[Get('/api/articles/{id}')]
    public function show(int $id): ResponseInterface
    {
        $article = $this->articles->find($id);

        if ($article === null) {
            return new JsonResponse(
                data: ['error' => 'Article not found'],
                status: 404,
            );
        }

        return new JsonResponse(data: $article);
    }

    #[Post('/api/articles')]
    #[Middleware(AuthMiddleware::class)]
    public function store(RequestInterface $request): ResponseInterface
    {
        $data = $this->validator->validate($request->json(), [
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'body' => ['required', 'string'],
        ]);

        $id = $this->articles->create([
            ...$data,
            'author_email' => $request->user()->email,
            'created_at' => new DateTimeImmutable(),
            'updated_at' => new DateTimeImmutable(),
        ]);

        return new JsonResponse(
            data: $this->articles->find($id),
            status: 201,
        );
    }

    #[Put('/api/articles/{id}')]
    #[Middleware(AuthMiddleware::class)]
    public function update(int $id, RequestInterface $request): ResponseInterface
    {
        $data = $this->validator->validate($request->json(), [
            'title' => ['string', 'min:3', 'max:200'],
            'body' => ['string'],
        ]);

        $this->articles->update($id, [
            ...$data,
            'updated_at' => new DateTimeImmutable(),
        ]);

        return new JsonResponse(data: $this->articles->find($id));
    }

    #[Delete('/api/articles/{id}')]
    #[Middleware(AuthMiddleware::class)]
    public function destroy(int $id): ResponseInterface
    {
        $this->articles->delete($id);

        return new JsonResponse(status: 204);
    }
}
```

## Step 5: Test with cURL

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
- RESTful controller with full CRUD
- Request validation
- Token-based authentication middleware
- Proper HTTP status codes

## Next Steps

- [CORS](/docs/packages/cors/) — enable cross-origin requests
- [Rate Limiting](/docs/packages/rate-limiting/) — protect your endpoints
- [API package](/docs/packages/api/) — API-specific utilities
