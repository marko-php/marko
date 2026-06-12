<?php

declare(strict_types=1);

namespace Marko\Media\Service;

use Marko\Media\Contracts\AttachmentInterface;
use Marko\Media\Contracts\MediaAttachmentRepositoryInterface;
use Marko\Media\Contracts\MediaRepositoryInterface;
use Marko\Media\Entity\Media;

readonly class AttachmentManager implements AttachmentInterface
{
    public function __construct(
        private MediaAttachmentRepositoryInterface $attachmentRepository,
        private MediaRepositoryInterface $mediaRepository,
    ) {}

    public function attach(
        Media $media,
        string $attachableType,
        int|string $attachableId,
    ): void {
        $this->attachmentRepository->attach(
            (int) $media->id,
            $attachableType,
            $attachableId,
        );
    }

    public function detach(
        Media $media,
        string $attachableType,
        int|string $attachableId,
    ): void {
        $this->attachmentRepository->detach(
            (int) $media->id,
            $attachableType,
            $attachableId,
        );
    }

    /**
     * @return array<Media>
     */
    public function findByAttachable(
        string $attachableType,
        int|string $attachableId,
    ): array {
        $mediaIds = $this->attachmentRepository->findByAttachable(
            $attachableType,
            $attachableId,
        );

        if ($mediaIds === []) {
            return [];
        }

        $mediaById = [];
        foreach ($this->mediaRepository->findMany($mediaIds) as $media) {
            $mediaById[(int) $media->id] = $media;
        }

        $result = [];
        foreach ($mediaIds as $id) {
            if (isset($mediaById[$id])) {
                $result[] = $mediaById[$id];
            }
        }

        return $result;
    }
}
