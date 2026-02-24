<?php

declare(strict_types=1);

use Marko\Api\Resource\JsonResource;
use Marko\Routing\Http\Response;

readonly class TestEntity
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {}
}

class TestJsonResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
        ];
    }
}

it('wraps an entity and exposes it via the resource property', function (): void {
    $entity = new TestEntity(id: 1, name: 'Alice', email: 'alice@example.com');
    $resource = new TestJsonResource($entity);

    expect($resource->resource)->toBe($entity);
});

it('serializes entity fields to array via toArray method', function (): void {
    $entity = new TestEntity(id: 1, name: 'Alice', email: 'alice@example.com');
    $resource = new TestJsonResource($entity);

    expect($resource->toArray())->toBe(['id' => 1, 'name' => 'Alice']);
});

it('includes conditional fields when condition is true', function (): void {
    $entity = new TestEntity(id: 1, name: 'Alice', email: 'alice@example.com');

    $resource = new class ($entity) extends JsonResource
    {
        public function toArray(): array
        {
            return [
                'id' => $this->resource->id,
                'email' => $this->when(true, $this->resource->email),
            ];
        }
    };

    expect($resource->toResponse()->body())->toBe('{"data":{"id":1,"email":"alice@example.com"}}');
});

it('excludes conditional fields when condition is false', function (): void {
    $entity = new TestEntity(id: 1, name: 'Alice', email: 'alice@example.com');

    $resource = new class ($entity) extends JsonResource
    {
        public function toArray(): array
        {
            return [
                'id' => $this->resource->id,
                'email' => $this->when(false, $this->resource->email),
            ];
        }
    };

    expect($resource->toResponse()->body())->toBe('{"data":{"id":1}}');
});

it('omits fields with MissingValue from output array', function (): void {
    $entity = new TestEntity(id: 1, name: 'Alice', email: 'alice@example.com');

    $resource = new class ($entity) extends JsonResource
    {
        public function toArray(): array
        {
            return [
                'id' => $this->resource->id,
                'secret' => $this->missing(),
            ];
        }
    };

    expect($resource->toResponse()->body())->toBe('{"data":{"id":1}}');
});

it('returns JSON Response via toResponse with correct content type', function (): void {
    $entity = new TestEntity(id: 1, name: 'Alice', email: 'alice@example.com');
    $resource = new TestJsonResource($entity);

    $response = $resource->toResponse();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->headers())->toBe(['Content-Type' => 'application/json'])
        ->and($response->body())->toBe('{"data":{"id":1,"name":"Alice"}}');
});
