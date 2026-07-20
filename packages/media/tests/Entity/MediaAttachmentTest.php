<?php

declare(strict_types=1);

namespace Marko\Media\Tests\Entity;

use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Media\Entity\MediaAttachment;

it('builds full entity metadata for MediaAttachment without throwing', function (): void {
    $metadata = (new EntityMetadataFactory())->parse(MediaAttachment::class);

    expect($metadata->tableName)->toBe('media_attachments');
});

it('maps the polymorphic attachableId union type to a varchar column', function (): void {
    $metadata = (new EntityMetadataFactory())->parse(MediaAttachment::class);

    expect($metadata->properties['attachableId']->type)->toContain('int')
        ->and($metadata->properties['attachableId']->type)->toContain('string')
        ->and($metadata->properties['attachableId']->columnType)->toBe('varchar');
});
