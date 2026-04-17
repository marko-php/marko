---
title: Build a REST API
description: Create a JSON API with Marko --- routing, validation, and authentication.
---

Build a RESTful API for managing articles, complete with authentication, validation, and proper HTTP responses.

## What You'll Build

- A full CRUD JSON API for articles
- Token-based authentication for protected endpoints
- Request validation with meaningful error responses
- Proper HTTP status codes (200, 201, 204, 400, 403, 404, 422)

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

Marko reads `#[Table]`, `#[Column]`, and `#[Index]` to auto-generate migrations. `DateTimeImmutable` properties are hydrated from and persisted to the database automatically.

```php title="app/api/src/Entity/Article.php"
<?php

declare(strict_types=1);

namespace App\Api\Entity;

use DateTimeImmutable;
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
    public string $title = '';

    #[Column(type: 'TEXT')]
    public string $body = '';

    #[Column]
    public string $authorEmail = '';

    #[Column]
    public ?DateTimeImmutable $createdAt = null;

    #[Column]
    public ?DateTimeImmutable $updatedAt = null;
}
```

Generate and run the migration:

```bash
marko db:migrate
```

## Step 3: Create the Repository

Extend the base `Repository` class. It provides `find()`, `findAll()`, `findBy()`, `findOneBy()`, `save()`, and `delete()` with automatic entity hydration, dirty-field tracking on updates, and lifecycle events.

```php title="app/api/src/Repository/ArticleRepository.php"
<?php

declare(strict_types=1);

namespace App\Api\Repository;

use App\Api\Entity\Article;
use Marko\Database\Repository\Repository;

class ArticleRepository extends Repository
{
    protected const string ENTITY_CLASS = Article::class;

    /**
     * @return array<Article>
     */
    public function findLatest(): array
    {
        return $this->query()
            ->orderBy('created_at', 'DESC')
            ->getEntities();
    }
}
```

## Step 4: Register the Module

```php title="app/api/composer.json"
{
    "name": "app/api",
    "autoload": {
        "psr-4": {
            "App\\Api\\": "src/"
        }
    }
}
```

No `module.php` is needed --- the controller and repository are autowired from their constructor signatures.

## Step 5: Build the Controller

The controller is a stateless service, so it's a `readonly class`. Every mutation validates input, enforces ownership, and returns proper HTTP status codes.

```php title="app/api/src/Controller/ArticleController.php"
<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Api\Entity\Article;
use App\Api\Repository\ArticleRepository;
use DateTimeImmutable;
use JsonException;
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

readonly class ArticleController
{
    public function __construct(
        private ArticleRepository $articleRepository,
        private ValidatorInterface $validator,
        private AuthManager $authManager,
    ) {}

    /**
     * @throws JsonException
     */
    #[Get('/api/articles')]
    public function index(): Response
    {
        return Response::json(data: $this->articleRepository->findLatest());
    }

    /**
     * @throws JsonException
     */
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

    /**
     * @throws JsonException
     */
    #[Post('/api/articles')]
    #[Middleware(AuthMiddleware::class)]
    public function store(Request $request): Response
    {
        $data = $this->decodeBody($request);

        if ($data === null) {
            return Response::json(
                data: ['error' => 'Invalid JSON body'],
                statusCode: 400,
            );
        }

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

        $now = new DateTimeImmutable();
        $article = new Article();
        $article->title = $data['title'];
        $article->body = $data['body'];
        $article->authorEmail = (string) $this->authManager->user()?->getAuthIdentifier();
        $article->createdAt = $now;
        $article->updatedAt = $now;

        $this->articleRepository->save($article);

        return Response::json(data: $article, statusCode: 201);
    }

    /**
     * @throws JsonException
     */
    #[Put('/api/articles/{id}')]
    #[Middleware(AuthMiddleware::class)]
    public function update(int $id, Request $request): Response
    {
        $article = $this->articleRepository->find($id);

        if ($article === null) {
            return Response::json(
                data: ['error' => 'Article not found'],
                statusCode: 404,
            );
        }

        if ($article->authorEmail !== $this->authManager->user()?->getAuthIdentifier()) {
            return Response::json(
                data: ['error' => 'Forbidden'],
                statusCode: 403,
            );
        }

        $data = $this->decodeBody($request);

        if ($data === null) {
            return Response::json(
                data: ['error' => 'Invalid JSON body'],
                statusCode: 400,
            );
        }

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

        if (isset($data['title'])) {
            $article->title = $data['title'];
        }

        if (isset($data['body'])) {
            $article->body = $data['body'];
        }

        $article->updatedAt = new DateTimeImmutable();

        $this->articleRepository->save($article);

        return Response::json(data: $article);
    }

    /**
     * @throws JsonException
     */
    #[Delete('/api/articles/{id}')]
    #[Middleware(AuthMiddleware::class)]
    public function destroy(int $id): Response
    {
        $article = $this->articleRepository->find($id);

        if ($article === null) {
            return Response::json(
                data: ['error' => 'Article not found'],
                statusCode: 404,
            );
        }

        if ($article->authorEmail !== $this->authManager->user()?->getAuthIdentifier()) {
            return Response::json(
                data: ['error' => 'Forbidden'],
                statusCode: 403,
            );
        }

        $this->articleRepository->delete($article);

        return Response::json(data: null, statusCode: 204);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeBody(Request $request): ?array
    {
        try {
            $decoded = json_decode($request->body(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
```

## Step 6: Start the Server

```bash
marko up
```

## Step 7: Test with cURL

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

# Update (owner only)
curl -X PUT http://localhost:8000/api/articles/1 \
    -H "Authorization: Bearer YOUR_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"title": "Updated"}'

# Delete (owner only)
curl -X DELETE http://localhost:8000/api/articles/1 \
    -H "Authorization: Bearer YOUR_TOKEN"
```

## What You've Learned

- Minimal Marko installation for APIs (no views, no sessions)
- Entity-driven database schemas with `#[Table]` and `#[Column]` attributes
- Base `Repository` class with automatic entity hydration and lifecycle events
- RESTful controller as a `readonly class` (stateless service)
- Request validation with [`ValidatorInterface`](/docs/packages/validation/)
- Token-based authentication with [`AuthMiddleware`](/docs/packages/authentication/)
- Ownership enforcement on mutations
- Proper HTTP status codes using [`Response::json()`](/docs/packages/routing/)
- Declared `@throws` on every method that propagates a checked exception

## Next Steps

- [Build a Blog](/docs/tutorials/build-a-blog/) --- build a full blog application
- [Create a Custom Module](/docs/tutorials/custom-module/) --- build a reusable Composer package
