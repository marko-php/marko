<?php

declare(strict_types=1);

use Marko\Filesystem\Contracts\DirectoryListingInterface;
use Marko\Filesystem\Values\DirectoryEntry;
use Marko\Filesystem\Values\DirectoryListing;

it('implements DirectoryListingInterface', function () {
    $listing = new DirectoryListing([]);

    expect($listing)->toBeInstanceOf(DirectoryListingInterface::class);
});

it('returns all entries', function () {
    $entries = [
        new DirectoryEntry('file1.txt', false, 100, 1234567890),
        new DirectoryEntry('file2.txt', false, 200, 1234567890),
        new DirectoryEntry('subdir', true, 0, 1234567890),
    ];

    $listing = new DirectoryListing($entries);

    expect($listing->entries())->toHaveCount(3)
        ->and($listing->entries())->toBe($entries);
});

it('filters files only', function () {
    $entries = [
        new DirectoryEntry('file1.txt', false, 100, 1234567890),
        new DirectoryEntry('subdir', true, 0, 1234567890),
        new DirectoryEntry('file2.txt', false, 200, 1234567890),
    ];

    $listing = new DirectoryListing($entries);
    $files = $listing->files();

    expect($files)->toHaveCount(2)
        ->and($files[0]->path)->toBe('file1.txt')
        ->and($files[1]->path)->toBe('file2.txt');
});

it('filters directories only', function () {
    $entries = [
        new DirectoryEntry('file1.txt', false, 100, 1234567890),
        new DirectoryEntry('subdir1', true, 0, 1234567890),
        new DirectoryEntry('subdir2', true, 0, 1234567890),
    ];

    $listing = new DirectoryListing($entries);
    $directories = $listing->directories();

    expect($directories)->toHaveCount(2)
        ->and($directories[0]->path)->toBe('subdir1')
        ->and($directories[1]->path)->toBe('subdir2');
});

it('is iterable', function () {
    $entries = [
        new DirectoryEntry('file1.txt', false, 100, 1234567890),
        new DirectoryEntry('file2.txt', false, 200, 1234567890),
    ];

    $listing = new DirectoryListing($entries);
    $paths = [];

    foreach ($listing as $entry) {
        $paths[] = $entry->path;
    }

    expect($paths)->toBe(['file1.txt', 'file2.txt']);
});

it('handles empty listing', function () {
    $listing = new DirectoryListing([]);

    expect($listing->entries())->toBe([])
        ->and($listing->files())->toBe([])
        ->and($listing->directories())->toBe([]);
});
