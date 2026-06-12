<?php

declare(strict_types=1);

namespace Marko\Media\Tests\Service;

use Marko\Media\Contracts\MediaAttachmentRepositoryInterface;
use Marko\Media\Contracts\MediaRepositoryInterface;
use Marko\Media\Entity\Media;
use Marko\Media\Service\AttachmentManager;

function makeAttachMedia(
    int $id = 1,
    string $path = '2024/01/abc123.jpg',
): Media {
    $media = new Media();
    $media->id = $id;
    $media->filename = 'abc123.jpg';
    $media->originalFilename = 'photo.jpg';
    $media->mimeType = 'image/jpeg';
    $media->size = 1024;
    $media->disk = 'local';
    $media->path = $path;

    return $media;
}

function makeAttachmentRepository(): MediaAttachmentRepositoryInterface
{
    return new class () implements MediaAttachmentRepositoryInterface
    {
        /** @var array<int, array{mediaId: int, attachableType: string, attachableId: int|string}> */
        public array $attachments = [];

        public function attach(
            int $mediaId,
            string $attachableType,
            int|string $attachableId,
        ): void {
            $this->attachments[] = [
                'mediaId' => $mediaId,
                'attachableType' => $attachableType,
                'attachableId' => $attachableId,
            ];
        }

        public function detach(
            int $mediaId,
            string $attachableType,
            int|string $attachableId,
        ): void {
            $this->attachments = array_values(array_filter(
                $this->attachments,
                fn ($a) => !($a['mediaId'] === $mediaId
                    && $a['attachableType'] === $attachableType
                    && $a['attachableId'] === $attachableId),
            ));
        }

        public function findByAttachable(
            string $attachableType,
            int|string $attachableId,
        ): array {
            return array_values(array_map(
                fn ($a) => $a['mediaId'],
                array_filter(
                    $this->attachments,
                    fn ($a) => $a['attachableType'] === $attachableType
                        && $a['attachableId'] === $attachableId,
                ),
            ));
        }
    };
}

function makeMediaRepository(
    Media ...$media,
): MediaRepositoryInterface {
    return new class ($media) implements MediaRepositoryInterface
    {
        /** @var array<int, Media> */
        private array $storage;

        /** @param array<Media> $media */
        public function __construct(
            array $media,
        ) {
            $this->storage = [];
            foreach ($media as $m) {
                $this->storage[(int) $m->id] = $m;
            }
        }

        public function save(
            Media $media,
        ): Media {
            $this->storage[(int) $media->id] = $media;

            return $media;
        }

        public function delete(
            int $id,
        ): void {
            unset($this->storage[$id]);
        }

        public function find(
            int $id,
        ): ?Media {
            return $this->storage[$id] ?? null;
        }

        /**
         * @param array<int> $ids
         * @return array<Media>
         */
        public function findMany(
            array $ids,
        ): array {
            return array_values(array_filter(
                array_map(fn (int $id) => $this->storage[$id] ?? null, $ids),
                fn ($m) => $m !== null,
            ));
        }
    };
}

function makeTrackingMediaRepository(
    Media ...$media,
): MediaRepositoryInterface {
    return new class ($media) implements MediaRepositoryInterface
    {
        /** @var array<int, Media> */
        private array $storage;

        public int $findCallCount = 0;

        public int $findManyCallCount = 0;

        /** @param array<Media> $media */
        public function __construct(
            array $media,
        ) {
            $this->storage = [];
            foreach ($media as $m) {
                $this->storage[(int) $m->id] = $m;
            }
        }

        public function save(
            Media $media,
        ): Media {
            $this->storage[(int) $media->id] = $media;

            return $media;
        }

        public function delete(
            int $id,
        ): void {
            unset($this->storage[$id]);
        }

        public function find(
            int $id,
        ): ?Media {
            ++$this->findCallCount;

            return $this->storage[$id] ?? null;
        }

        /**
         * @param array<int> $ids
         * @return array<Media>
         */
        public function findMany(
            array $ids,
        ): array {
            ++$this->findManyCallCount;

            return array_values(array_filter(
                array_map(fn (int $id) => $this->storage[$id] ?? null, $ids),
                fn ($m) => $m !== null,
            ));
        }
    };
}

it('returns the media entities for all attached ids', function (): void {
    $media1 = makeAttachMedia(id: 1, path: '2024/01/img1.jpg');
    $media2 = makeAttachMedia(id: 2, path: '2024/01/img2.jpg');
    $attachmentRepo = makeAttachmentRepository();
    $mediaRepo = makeTrackingMediaRepository($media1, $media2);

    $manager = new AttachmentManager(
        attachmentRepository: $attachmentRepo,
        mediaRepository: $mediaRepo,
    );

    $manager->attach($media1, 'App\Post', 10);
    $manager->attach($media2, 'App\Post', 10);

    $found = $manager->findByAttachable('App\Post', 10);

    expect($found)->toHaveCount(2)
        ->and($found[0])->toBeInstanceOf(Media::class)
        ->and($found[1])->toBeInstanceOf(Media::class);
});

it('returns media in the order of the attachment id list', function (): void {
    $media1 = makeAttachMedia(id: 1, path: '2024/01/img1.jpg');
    $media2 = makeAttachMedia(id: 2, path: '2024/01/img2.jpg');
    $media3 = makeAttachMedia(id: 3, path: '2024/01/img3.jpg');
    $attachmentRepo = makeAttachmentRepository();
    $mediaRepo = makeTrackingMediaRepository($media1, $media2, $media3);

    $manager = new AttachmentManager(
        attachmentRepository: $attachmentRepo,
        mediaRepository: $mediaRepo,
    );

    $manager->attach($media3, 'App\Post', 10);
    $manager->attach($media1, 'App\Post', 10);
    $manager->attach($media2, 'App\Post', 10);

    $found = $manager->findByAttachable('App\Post', 10);

    expect($found)->toHaveCount(3)
        ->and($found[0]->id)->toBe(3)
        ->and($found[1]->id)->toBe(1)
        ->and($found[2]->id)->toBe(2);
});

it('skips ids that have no matching media row', function (): void {
    $media1 = makeAttachMedia(id: 1, path: '2024/01/img1.jpg');
    $attachmentRepo = makeAttachmentRepository();
    $mediaRepo = makeTrackingMediaRepository($media1);

    $manager = new AttachmentManager(
        attachmentRepository: $attachmentRepo,
        mediaRepository: $mediaRepo,
    );

    $manager->attach($media1, 'App\Post', 10);
    // Manually inject a dangling attachment id (99) that has no media row
    $attachmentRepo->attachments[] = ['mediaId' => 99, 'attachableType' => 'App\Post', 'attachableId' => 10];

    $found = $manager->findByAttachable('App\Post', 10);

    expect($found)->toHaveCount(1)
        ->and($found[0]->id)->toBe(1);
});

it('returns an empty array when there are no attachments', function (): void {
    $attachmentRepo = makeAttachmentRepository();
    $mediaRepo = makeTrackingMediaRepository();

    $manager = new AttachmentManager(
        attachmentRepository: $attachmentRepo,
        mediaRepository: $mediaRepo,
    );

    $found = $manager->findByAttachable('App\Post', 10);

    expect($found)->toBeEmpty();
});

it('resolves all attachments with a single findMany call and never calls find per id', function (): void {
    $media1 = makeAttachMedia(id: 1);
    $media2 = makeAttachMedia(id: 2);
    $media3 = makeAttachMedia(id: 3);
    $attachmentRepo = makeAttachmentRepository();
    $mediaRepo = makeTrackingMediaRepository($media1, $media2, $media3);

    $manager = new AttachmentManager(
        attachmentRepository: $attachmentRepo,
        mediaRepository: $mediaRepo,
    );

    $manager->attach($media1, 'App\Post', 10);
    $manager->attach($media2, 'App\Post', 10);
    $manager->attach($media3, 'App\Post', 10);

    $manager->findByAttachable('App\Post', 10);

    expect($mediaRepo->findCallCount)->toBe(0)
        ->and($mediaRepo->findManyCallCount)->toBe(1);
});

it('does not invoke findMany when there are no attachment ids', function (): void {
    $attachmentRepo = makeAttachmentRepository();
    $mediaRepo = makeTrackingMediaRepository();

    $manager = new AttachmentManager(
        attachmentRepository: $attachmentRepo,
        mediaRepository: $mediaRepo,
    );

    $manager->findByAttachable('App\Post', 10);

    expect($mediaRepo->findManyCallCount)->toBe(0);
});

it('retrieves all media attached to a given entity', function (): void {
    $media1 = makeAttachMedia(id: 1, path: '2024/01/img1.jpg');
    $media2 = makeAttachMedia(id: 2, path: '2024/01/img2.jpg');
    $attachmentRepo = makeAttachmentRepository();
    $mediaRepo = makeMediaRepository($media1, $media2);

    $manager = new AttachmentManager(
        attachmentRepository: $attachmentRepo,
        mediaRepository: $mediaRepo,
    );

    $manager->attach($media1, 'App\Post', 10);
    $manager->attach($media2, 'App\Post', 10);

    $found = $manager->findByAttachable('App\Post', 10);

    expect($found)->toHaveCount(2)
        ->and($found[0])->toBeInstanceOf(Media::class)
        ->and($found[0]->id)->toBe(1)
        ->and($found[1]->id)->toBe(2);
});

it('attaches media to an entity via attachable_type and attachable_id', function (): void {
    $media = makeAttachMedia(id: 1);
    $attachmentRepo = makeAttachmentRepository();
    $mediaRepo = makeMediaRepository($media);

    $manager = new AttachmentManager(
        attachmentRepository: $attachmentRepo,
        mediaRepository: $mediaRepo,
    );

    $manager->attach($media, 'App\Post', 42);

    expect($attachmentRepo->attachments)->toHaveCount(1)
        ->and($attachmentRepo->attachments[0]['mediaId'])->toBe(1)
        ->and($attachmentRepo->attachments[0]['attachableType'])->toBe('App\Post')
        ->and($attachmentRepo->attachments[0]['attachableId'])->toBe(42);
});

it('detaches media from an entity', function (): void {
    $media = makeAttachMedia(id: 1);
    $attachmentRepo = makeAttachmentRepository();
    $mediaRepo = makeMediaRepository($media);

    $manager = new AttachmentManager(
        attachmentRepository: $attachmentRepo,
        mediaRepository: $mediaRepo,
    );

    $manager->attach($media, 'App\Post', 42);

    expect($attachmentRepo->attachments)->toHaveCount(1);

    $manager->detach($media, 'App\Post', 42);

    expect($attachmentRepo->attachments)->toBeEmpty();
});
