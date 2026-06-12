<?php

declare(strict_types=1);

namespace Marko\Media\Contracts;

use Marko\Media\Entity\Media;

interface MediaRepositoryInterface
{
    public function save(
        Media $media,
    ): Media;

    public function delete(
        int $id,
    ): void;

    public function find(
        int $id,
    ): ?Media;

    /**
     * Return all Media entities whose ids are in the given list.
     *
     * Empty input MUST return an empty array without issuing a query.
     * Ids that have no matching row are silently skipped.
     * The returned array order is unspecified; callers are responsible
     * for reordering if a specific order is required.
     *
     * A single-query implementation (e.g. WHERE id IN (...)) is the
     * recommended approach for consumer implementations.
     *
     * @param array<int> $ids
     * @return array<Media>
     */
    public function findMany(
        array $ids,
    ): array;
}
