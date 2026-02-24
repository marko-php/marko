<?php

declare(strict_types=1);

namespace Marko\Media\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table(name: 'media_attachments')]
class MediaAttachment extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column('media_id')]
    public int $mediaId = 0;

    #[Column('attachable_type', length: 255)]
    public string $attachableType = '';

    #[Column('attachable_id', length: 255)]
    public int|string $attachableId = 0;
}
