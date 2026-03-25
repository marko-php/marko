---
title: marko/api
description: Transform entities into consistent JSON API responses --- define resource classes once and use them everywhere in your API controllers.
---

Transform entities into consistent JSON API responses --- define resource classes once and use them everywhere in your API controllers. Resource classes map your domain entities to JSON output, keeping serialization logic out of controllers and models. Every response wraps its payload in a `data` key for consistency. Collections add a `meta` key automatically when pagination is attached. Conditional fields let you include or omit data based on runtime context without cluttering your `toArray()` logic.

## Installation

```bash
composer require marko/api
```

## Usage

### Defining a Resource

Extend `JsonResource` and implement `toArray()` to define the field mapping:

```php
use Marko\Api\Resource\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'title'  => $this->resource->title,
            'slug'   => $this->resource->slug,
            'body'   => $this->resource->body,
            'author' => $this->resource->author,
        ];
    }
}
```

### Using a Resource in a Controller

Call `toResponse()` to get a `Response` with the data wrapped in `{"data": {...}}`:

```php
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;

class PostController
{
    public function __construct(
        private PostRepository $postRepository,
    ) {}

    #[Get('/posts/{slug}')]
    public function show(
        string $slug,
    ): Response {
        $post = $this->postRepository->findBySlug($slug);

        return new PostResource($post)->toResponse();
    }
}
```

The response body:

```json
{
    "data": {
        "title": "Hello World",
        "slug": "hello-world",
        "body": "...",
        "author": "Jane"
    }
}
```

### Resource Collections with Pagination

Pass an array of items and the resource class to `ResourceCollection`. Chain `withPagination()` to append pagination meta automatically:

```php
use Marko\Api\Resource\ResourceCollection;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;

class PostController
{
    public function __construct(
        private PostRepository $postRepository,
    ) {}

    #[Get('/posts')]
    public function index(): Response
    {
        $paginator = $this->postRepository->paginate(perPage: 15);

        return new ResourceCollection(
            $paginator->items(),
            PostResource::class,
        )
            ->withPagination($paginator)
            ->toResponse();
    }
}
```

The response body:

```json
{
    "data": [
        { "title": "Hello World", "slug": "hello-world", "body": "...", "author": "Jane" }
    ],
    "meta": {
        "page": 1,
        "per_page": 15,
        "total": 42,
        "total_pages": 3
    }
}
```

Use `additional()` to merge extra keys into `meta`:

```php
use Marko\Api\Resource\ResourceCollection;

return new ResourceCollection($items, PostResource::class)
    ->withPagination($paginator)
    ->additional(['category' => 'news'])
    ->toResponse();
```

### Conditional Fields

Use `when()` to include a field only when a condition is true. When false, the field is omitted entirely from the response:

```php
use Marko\Api\Resource\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'title'     => $this->resource->title,
            'slug'      => $this->resource->slug,
            'body'      => $this->resource->body,
            'author'    => $this->resource->author,
            'edit_url'  => $this->when(
                $this->resource->isEditable,
                '/posts/' . $this->resource->slug . '/edit',
            ),
        ];
    }
}
```

When `isEditable` is `false`, `edit_url` does not appear in the output at all.

### Omitting Fields with MissingValue

Use `missing()` as a sentinel to unconditionally exclude a field. This is useful when building resources dynamically:

```php
use Marko\Api\Resource\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'title'    => $this->resource->title,
            'slug'     => $this->resource->slug,
            'internal' => $this->missing(),
        ];
    }
}
```

`internal` is always omitted from the JSON output.

## Customization

To override the resource response format application-wide, use a [Preference](/docs/packages/core/) to extend `JsonResource` or `ResourceCollection`:

```php
use Marko\Core\Attributes\Preference;
use Marko\Api\Resource\JsonResource;
use Marko\Routing\Http\Response;

#[Preference(replaces: JsonResource::class)]
class WrappedJsonResource extends JsonResource
{
    public function toResponse(): Response
    {
        return Response::json([
            'data'    => $this->filterArray($this->toArray()),
            'version' => '1.0',
        ]);
    }
}
```

## API Reference

### JsonResource (abstract)

```php
use Marko\Api\Contracts\ResourceInterface;
use Marko\Api\Value\ConditionalValue;
use Marko\Api\Value\MissingValue;
use Marko\Routing\Http\Response;

abstract class JsonResource implements ResourceInterface
{
    public function __construct(public readonly mixed $resource);
    abstract public function toArray(): array;
    public function toResponse(): Response;
    protected function when(bool $condition, mixed $value): ConditionalValue;
    protected function missing(): MissingValue;
}
```

### ResourceCollection

```php
use Marko\Api\Contracts\ResourceCollectionInterface;
use Marko\Pagination\Contracts\PaginatorInterface;
use Marko\Routing\Http\Response;

class ResourceCollection implements ResourceCollectionInterface
{
    public function __construct(array $items, string $resourceClass);
    public function toArray(): array;
    public function toResponse(): Response;
    public function withPagination(PaginatorInterface $paginator): static;
    public function additional(array $meta): static;
}
```

### ResourceInterface

```php
use Marko\Routing\Http\Response;

interface ResourceInterface
{
    public function toArray(): array;
    public function toResponse(): Response;
}
```

### ResourceCollectionInterface

```php
use Marko\Pagination\Contracts\PaginatorInterface;
use Marko\Routing\Http\Response;

interface ResourceCollectionInterface
{
    public function toArray(): array;
    public function toResponse(): Response;
    public function withPagination(PaginatorInterface $paginator): static;
}
```

### ConditionalValue

```php
class ConditionalValue
{
    public function __construct(public readonly bool $condition, public readonly mixed $value);
    public function resolve(): mixed;
}
```

### MissingValue

```php
class MissingValue {}
```

### ApiResourceException

```php
use Marko\Api\Exceptions\ApiResourceException;
use Marko\Core\Exceptions\MarkoException;

class ApiResourceException extends MarkoException {}
```

Inherits `getContext()` and `getSuggestion()` from `MarkoException`.
