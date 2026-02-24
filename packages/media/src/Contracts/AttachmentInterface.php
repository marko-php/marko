<?php

declare(strict_types=1);

namespace Marko\Media\Contracts;

use Marko\Media\Entity\Media;

interface AttachmentInterface
{
    /**
     * Associate a Media entity with an attachable entity.
     */
    public function attach(
        Media $media,
        string $attachableType,
        int|string $attachableId,
    ): void;

    /**
     * Dissociate a Media entity from an attachable entity.
     */
    public function detach(
        Media $media,
        string $attachableType,
        int|string $attachableId,
    ): void;

    /**
     * Find all Media entities associated with a given attachable entity.
     *
     * @return array<Media>
     */
    public function findByAttachable(
        string $attachableType,
        int|string $attachableId,
    ): array;
}
