<?php

declare(strict_types=1);

use Marko\Filesystem\Values\DirectoryEntry;

it('stores path correctly', function () {
    $entry = new DirectoryEntry(
        path: 'uploads/file.txt',
        isDirectory: false,
        size: 1024,
        lastModified: 1234567890,
    );

    expect($entry->path)->toBe('uploads/file.txt');
});

it('stores isDirectory correctly', function () {
    $entry = new DirectoryEntry(
        path: 'uploads',
        isDirectory: true,
        size: 0,
        lastModified: 1234567890,
    );

    expect($entry->isDirectory)->toBeTrue();
});

it('stores size correctly', function () {
    $entry = new DirectoryEntry(
        path: 'file.txt',
        isDirectory: false,
        size: 2048,
        lastModified: 1234567890,
    );

    expect($entry->size)->toBe(2048);
});

it('stores lastModified correctly', function () {
    $entry = new DirectoryEntry(
        path: 'file.txt',
        isDirectory: false,
        size: 1024,
        lastModified: 1234567890,
    );

    expect($entry->lastModified)->toBe(1234567890);
});

it('identifies files correctly', function () {
    $entry = new DirectoryEntry(
        path: 'file.txt',
        isDirectory: false,
        size: 1024,
        lastModified: 1234567890,
    );

    expect($entry->isFile())->toBeTrue()
        ->and($entry->isDirectory)->toBeFalse();
});

it('identifies directories correctly', function () {
    $entry = new DirectoryEntry(
        path: 'uploads',
        isDirectory: true,
        size: 0,
        lastModified: 1234567890,
    );

    expect($entry->isDirectory)->toBeTrue()
        ->and($entry->isFile())->toBeFalse();
});

it('returns basename correctly', function () {
    $entry = new DirectoryEntry(
        path: 'uploads/documents/report.pdf',
        isDirectory: false,
        size: 1024,
        lastModified: 1234567890,
    );

    expect($entry->basename())->toBe('report.pdf');
});
