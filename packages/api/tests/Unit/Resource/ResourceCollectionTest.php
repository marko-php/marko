<?php

declare(strict_types=1);

use Marko\Api\Resource\JsonResource;
use Marko\Api\Resource\ResourceCollection;
use Marko\Pagination\Contracts\PaginatorInterface;
use Marko\Routing\Http\Response;

readonly class CollectionTestEntity
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}

class CollectionTestResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
        ];
    }
}

it('wraps an array of items using specified resource class', function (): void {
    $items = [
        new CollectionTestEntity(id: 1, name: 'Alice'),
        new CollectionTestEntity(id: 2, name: 'Bob'),
    ];

    $collection = new ResourceCollection($items, CollectionTestResource::class);

    expect($collection)->toBeInstanceOf(ResourceCollection::class);
});

it('serializes all items via their resource toArray method', function (): void {
    $items = [
        new CollectionTestEntity(id: 1, name: 'Alice'),
        new CollectionTestEntity(id: 2, name: 'Bob'),
    ];

    $collection = new ResourceCollection($items, CollectionTestResource::class);

    expect($collection->toArray())->toBe([
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ]);
});

it('returns JSON response with data and meta keys via toResponse', function (): void {
    $items = [
        new CollectionTestEntity(id: 1, name: 'Alice'),
        new CollectionTestEntity(id: 2, name: 'Bob'),
    ];

    $paginator = new class () implements PaginatorInterface
    {
        public function items(): array
        {
            return [];
        }

        public function total(): int
        {
            return 2;
        }

        public function perPage(): int
        {
            return 10;
        }

        public function currentPage(): int
        {
            return 1;
        }

        public function lastPage(): int
        {
            return 1;
        }

        public function hasMorePages(): bool
        {
            return false;
        }

        public function previousPage(): ?int
        {
            return null;
        }

        public function nextPage(): ?int
        {
            return null;
        }

        public function toArray(): array
        {
            return [];
        }
    };

    $collection = new ResourceCollection($items, CollectionTestResource::class)
        ->withPagination($paginator);

    $response = $collection->toResponse();
    $decoded = json_decode($response->body(), true);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($decoded)->toHaveKey('data')
        ->and($decoded)->toHaveKey('meta')
        ->and($decoded['data'])->toBe([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ])
        ->and($decoded['meta'])->toBe([
            'page' => 1,
            'per_page' => 10,
            'total' => 2,
            'total_pages' => 1,
        ]);
});

it('supports additional meta data via additional method', function (): void {
    $items = [
        new CollectionTestEntity(id: 1, name: 'Alice'),
    ];

    $paginator = new class () implements PaginatorInterface
    {
        public function items(): array
        {
            return [];
        }

        public function total(): int
        {
            return 50;
        }

        public function perPage(): int
        {
            return 10;
        }

        public function currentPage(): int
        {
            return 2;
        }

        public function lastPage(): int
        {
            return 5;
        }

        public function hasMorePages(): bool
        {
            return true;
        }

        public function previousPage(): ?int
        {
            return 1;
        }

        public function nextPage(): ?int
        {
            return 3;
        }

        public function toArray(): array
        {
            return [];
        }
    };

    $collection = new ResourceCollection($items, CollectionTestResource::class)
        ->withPagination($paginator)
        ->additional(['filter' => 'active', 'sort' => 'name']);

    $response = $collection->toResponse();
    $decoded = json_decode($response->body(), true);

    expect($decoded['meta'])->toBe([
        'page' => 2,
        'per_page' => 10,
        'total' => 50,
        'total_pages' => 5,
        'filter' => 'active',
        'sort' => 'name',
    ]);
});

it('includes pagination metadata when paginator is provided', function (): void {
    $items = [
        new CollectionTestEntity(id: 1, name: 'Alice'),
    ];

    $paginator = new class () implements PaginatorInterface
    {
        public function items(): array
        {
            return [];
        }

        public function total(): int
        {
            return 100;
        }

        public function perPage(): int
        {
            return 15;
        }

        public function currentPage(): int
        {
            return 1;
        }

        public function lastPage(): int
        {
            return 7;
        }

        public function hasMorePages(): bool
        {
            return true;
        }

        public function previousPage(): ?int
        {
            return null;
        }

        public function nextPage(): ?int
        {
            return 2;
        }

        public function toArray(): array
        {
            return [];
        }
    };

    $collection = new ResourceCollection($items, CollectionTestResource::class);
    $collection->withPagination($paginator);

    $response = $collection->toResponse();
    $decoded = json_decode($response->body(), true);

    expect($decoded['meta'])->toBe([
        'page' => 1,
        'per_page' => 15,
        'total' => 100,
        'total_pages' => 7,
    ]);
});
