<?php

declare(strict_types=1);

namespace Marko\Media\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('media')]
class Media extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(length: 255)]
    public string $filename = '';

    #[Column('original_filename', length: 255)]
    public string $originalFilename = '';

    #[Column('mime_type', length: 100)]
    public string $mimeType = '';

    #[Column]
    public int $size = 0;

    #[Column(length: 50)]
    public string $disk = '';

    #[Column(length: 1000)]
    public string $path = '';

    #[Column(type: 'TEXT')]
    public ?string $metadata = null;

    #[Column('created_at')]
    public ?string $createdAt = null;

    #[Column('updated_at')]
    public ?string $updatedAt = null;
}
