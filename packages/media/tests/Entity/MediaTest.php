<?php

declare(strict_types=1);

namespace Marko\Media\Tests\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Media\Entity\Media;
use ReflectionClass;

it('defines Media entity with table and column attributes for all media properties', function (): void {
    $reflection = new ReflectionClass(Media::class);

    // Verify entity extends Entity base class
    // Verify Table attribute
    $tableAttributes = $reflection->getAttributes(Table::class);
    $tableAttr = $tableAttributes[0]->newInstance();
    expect($reflection->getParentClass()->getName())->toBe(Entity::class)
        ->and($tableAttributes)->toHaveCount(1)
        ->and($tableAttr->name)->toBe('media');

    // Verify id property
    $idProp = $reflection->getProperty('id');
    $idAttrs = $idProp->getAttributes(Column::class);
    $idColumn = $idAttrs[0]->newInstance();
    expect($idAttrs)->toHaveCount(1)
        ->and($idColumn->primaryKey)->toBeTrue()
        ->and($idColumn->autoIncrement)->toBeTrue()
        ->and($idProp->getType()->allowsNull())->toBeTrue();

    // Verify filename property
    $filenameProp = $reflection->getProperty('filename');
    $filenameAttrs = $filenameProp->getAttributes(Column::class);
    $filenameColumn = $filenameAttrs[0]->newInstance();
    expect($filenameAttrs)->toHaveCount(1)
        ->and($filenameColumn->length)->toBe(255);

    // Verify original_filename property
    $originalFilenameProp = $reflection->getProperty('originalFilename');
    $originalFilenameAttrs = $originalFilenameProp->getAttributes(Column::class);
    $originalFilenameColumn = $originalFilenameAttrs[0]->newInstance();
    expect($originalFilenameAttrs)->toHaveCount(1)
        ->and($originalFilenameColumn->name)->toBe('original_filename')
        ->and($originalFilenameColumn->length)->toBe(255);

    // Verify mime_type property
    $mimeTypeProp = $reflection->getProperty('mimeType');
    $mimeTypeAttrs = $mimeTypeProp->getAttributes(Column::class);
    $mimeTypeColumn = $mimeTypeAttrs[0]->newInstance();
    expect($mimeTypeAttrs)->toHaveCount(1)
        ->and($mimeTypeColumn->name)->toBe('mime_type')
        ->and($mimeTypeColumn->length)->toBe(100);

    // Verify size property
    $sizeProp = $reflection->getProperty('size');
    $sizeAttrs = $sizeProp->getAttributes(Column::class);
    expect($sizeAttrs)->toHaveCount(1);

    // Verify disk property
    $diskProp = $reflection->getProperty('disk');
    $diskAttrs = $diskProp->getAttributes(Column::class);
    $diskColumn = $diskAttrs[0]->newInstance();
    expect($diskAttrs)->toHaveCount(1)
        ->and($diskColumn->length)->toBe(50);

    // Verify path property
    $pathProp = $reflection->getProperty('path');
    $pathAttrs = $pathProp->getAttributes(Column::class);
    $pathColumn = $pathAttrs[0]->newInstance();
    expect($pathAttrs)->toHaveCount(1)
        ->and($pathColumn->length)->toBe(1000);

    // Verify metadata property (nullable TEXT)
    $metadataProp = $reflection->getProperty('metadata');
    $metadataAttrs = $metadataProp->getAttributes(Column::class);
    $metadataColumn = $metadataAttrs[0]->newInstance();
    expect($metadataAttrs)->toHaveCount(1)
        ->and($metadataColumn->type)->toBe('TEXT')
        ->and($metadataProp->getType()->allowsNull())->toBeTrue();

    // Verify created_at property
    $createdAtProp = $reflection->getProperty('createdAt');
    $createdAtAttrs = $createdAtProp->getAttributes(Column::class);
    $createdAtColumn = $createdAtAttrs[0]->newInstance();
    expect($createdAtAttrs)->toHaveCount(1)
        ->and($createdAtColumn->name)->toBe('created_at')
        ->and($createdAtProp->getType()->allowsNull())->toBeTrue();

    // Verify updated_at property
    $updatedAtProp = $reflection->getProperty('updatedAt');
    $updatedAtAttrs = $updatedAtProp->getAttributes(Column::class);
    $updatedAtColumn = $updatedAtAttrs[0]->newInstance();
    expect($updatedAtAttrs)->toHaveCount(1)
        ->and($updatedAtColumn->name)->toBe('updated_at')
        ->and($updatedAtProp->getType()->allowsNull())->toBeTrue();
});
