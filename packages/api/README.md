# marko/api

Transform entities into consistent JSON API responses --- define resource classes once and use them everywhere in your API controllers.

## Installation

```bash
composer require marko/api
```

## Quick Example

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

// In a controller:
return new PostResource($post)->toResponse();
// => {"data": {"title": "Hello World", ...}}
```

## Documentation

Full usage, API reference, and examples: [marko/api](https://marko.build/docs/packages/api/)
