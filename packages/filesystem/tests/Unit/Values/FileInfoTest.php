<?php

declare(strict_types=1);

use Marko\Filesystem\Values\FileInfo;

it('stores path correctly', function () {
    $info = new FileInfo(
        path: 'uploads/file.txt',
        size: 1024,
        lastModified: 1234567890,
        mimeType: 'text/plain',
        isDirectory: false,
        visibility: 'public',
    );

    expect($info->path)->toBe('uploads/file.txt');
});

it('stores size correctly', function () {
    $info = new FileInfo(
        path: 'file.txt',
        size: 2048,
        lastModified: 1234567890,
        mimeType: 'text/plain',
        isDirectory: false,
        visibility: 'public',
    );

    expect($info->size)->toBe(2048);
});

it('stores lastModified correctly', function () {
    $info = new FileInfo(
        path: 'file.txt',
        size: 1024,
        lastModified: 1234567890,
        mimeType: 'text/plain',
        isDirectory: false,
        visibility: 'public',
    );

    expect($info->lastModified)->toBe(1234567890);
});

it('stores mimeType correctly', function () {
    $info = new FileInfo(
        path: 'image.png',
        size: 1024,
        lastModified: 1234567890,
        mimeType: 'image/png',
        isDirectory: false,
        visibility: 'public',
    );

    expect($info->mimeType)->toBe('image/png');
});

it('identifies files correctly', function () {
    $info = new FileInfo(
        path: 'file.txt',
        size: 1024,
        lastModified: 1234567890,
        mimeType: 'text/plain',
        isDirectory: false,
        visibility: 'public',
    );

    expect($info->isFile())->toBeTrue()
        ->and($info->isDirectory)->toBeFalse();
});

it('identifies directories correctly', function () {
    $info = new FileInfo(
        path: 'uploads',
        size: 0,
        lastModified: 1234567890,
        mimeType: 'directory',
        isDirectory: true,
        visibility: 'public',
    );

    expect($info->isDirectory)->toBeTrue()
        ->and($info->isFile())->toBeFalse();
});

it('identifies public visibility', function () {
    $info = new FileInfo(
        path: 'file.txt',
        size: 1024,
        lastModified: 1234567890,
        mimeType: 'text/plain',
        isDirectory: false,
        visibility: 'public',
    );

    expect($info->isPublic())->toBeTrue()
        ->and($info->isPrivate())->toBeFalse();
});

it('identifies private visibility', function () {
    $info = new FileInfo(
        path: 'file.txt',
        size: 1024,
        lastModified: 1234567890,
        mimeType: 'text/plain',
        isDirectory: false,
        visibility: 'private',
    );

    expect($info->isPrivate())->toBeTrue()
        ->and($info->isPublic())->toBeFalse();
});

it('returns basename correctly', function () {
    $info = new FileInfo(
        path: 'uploads/documents/report.pdf',
        size: 1024,
        lastModified: 1234567890,
        mimeType: 'application/pdf',
        isDirectory: false,
        visibility: 'public',
    );

    expect($info->basename())->toBe('report.pdf');
});

it('returns extension correctly', function () {
    $info = new FileInfo(
        path: 'uploads/image.jpeg',
        size: 1024,
        lastModified: 1234567890,
        mimeType: 'image/jpeg',
        isDirectory: false,
        visibility: 'public',
    );

    expect($info->extension())->toBe('jpeg');
});

it('returns directory correctly', function () {
    $info = new FileInfo(
        path: 'uploads/documents/report.pdf',
        size: 1024,
        lastModified: 1234567890,
        mimeType: 'application/pdf',
        isDirectory: false,
        visibility: 'public',
    );

    expect($info->directory())->toBe('uploads/documents');
});
