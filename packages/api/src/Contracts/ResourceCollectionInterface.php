<?php

declare(strict_types=1);

namespace Marko\Api\Contracts;

use Marko\Pagination\Contracts\PaginatorInterface;
use Marko\Routing\Http\Response;

interface ResourceCollectionInterface
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(): array;

    /**
     * Transform the resource collection into a JSON HTTP response.
     */
    public function toResponse(): Response;

    /**
     * Associate a paginator with the resource collection.
     */
    public function withPagination(
        PaginatorInterface $paginator,
    ): static;
}
