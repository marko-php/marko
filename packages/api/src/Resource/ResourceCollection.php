<?php

declare(strict_types=1);

namespace Marko\Api\Resource;

use JsonException;
use Marko\Api\Contracts\ResourceCollectionInterface;
use Marko\Pagination\Contracts\PaginatorInterface;
use Marko\Routing\Http\Response;

class ResourceCollection implements ResourceCollectionInterface
{
    private ?PaginatorInterface $paginator = null;

    /** @var array<string, mixed> */
    private array $additionalMeta = [];

    /**
     * @param array $items
     * @param class-string<JsonResource> $resourceClass
     */
    public function __construct(
        private readonly array $items,
        private readonly string $resourceClass,
    ) {}

    /**
     * @return array<int|string, mixed>
     */
    public function toArray(): array
    {
        return array_map(
            fn (mixed $item): array => new $this->resourceClass($item)->toArray(),
            $this->items,
        );
    }

    /**
     * @throws JsonException
     */
    public function toResponse(): Response
    {
        $meta = $this->buildMeta();

        $payload = ['data' => $this->toArray()];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return Response::json($payload);
    }

    public function withPagination(
        PaginatorInterface $paginator,
    ): static {
        $this->paginator = $paginator;

        return $this;
    }

    /**
     * Merge extra keys into the meta section.
     *
     * @param array<string, mixed> $meta
     */
    public function additional(
        array $meta,
    ): static {
        $this->additionalMeta = array_merge($this->additionalMeta, $meta);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMeta(): array
    {
        $meta = [];

        if ($this->paginator !== null) {
            $meta = [
                'page' => $this->paginator->currentPage(),
                'per_page' => $this->paginator->perPage(),
                'total' => $this->paginator->total(),
                'total_pages' => $this->paginator->lastPage(),
            ];
        }

        return array_merge($meta, $this->additionalMeta);
    }
}
