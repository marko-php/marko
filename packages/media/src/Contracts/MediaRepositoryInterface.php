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
}
