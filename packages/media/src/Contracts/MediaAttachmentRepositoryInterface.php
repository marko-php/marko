<?php

declare(strict_types=1);

namespace Marko\Media\Contracts;

interface MediaAttachmentRepositoryInterface
{
    public function attach(
        int $mediaId,
        string $attachableType,
        int|string $attachableId,
    ): void;

    public function detach(
        int $mediaId,
        string $attachableType,
        int|string $attachableId,
    ): void;

    /**
     * @return array<int>
     */
    public function findByAttachable(
        string $attachableType,
        int|string $attachableId,
    ): array;
}
