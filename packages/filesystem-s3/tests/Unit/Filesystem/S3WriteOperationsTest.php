<?php

declare(strict_types=1);

use Aws\Result;
use GuzzleHttp\Psr7\Stream;
use Marko\Filesystem\S3\Filesystem\S3Filesystem;
use Marko\Filesystem\S3\Tests\Support\MockS3Client;

it('writes file contents to S3 with detected content type', function () {
    $client = MockS3Client::create([
        'putObject' => fn (array $args) => new Result([]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());
    $result = $filesystem->write('images/photo.jpg', 'binary data');

    expect($result)->toBeTrue()
        ->and($client->calls[0]['method'])->toBe('putObject')
        ->and($client->calls[0]['args']['Bucket'])->toBe('test-bucket')
        ->and($client->calls[0]['args']['Key'])->toBe('images/photo.jpg')
        ->and($client->calls[0]['args']['Body'])->toBe('binary data')
        ->and($client->calls[0]['args']['ContentType'])->toBe('image/jpeg');
});

it('writes file with custom content type override', function () {
    $client = MockS3Client::create([
        'putObject' => fn (array $args) => new Result([]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());
    $filesystem->write('data.bin', 'data', ['content_type' => 'application/custom']);

    expect($client->calls[0]['args']['ContentType'])->toBe('application/custom');
});

it('writes file with visibility ACL', function () {
    $client = MockS3Client::create([
        'putObject' => fn (array $args) => new Result([]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());
    $filesystem->write('public-file.txt', 'content', ['visibility' => 'public']);

    expect($client->calls[0]['args']['ACL'])->toBe('public-read');
});

it('writes stream to S3 using writeStream', function () {
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, 'stream data');
    rewind($stream);

    $client = MockS3Client::create([
        'putObject' => fn (array $args) => new Result([]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());
    $result = $filesystem->writeStream('file.txt', $stream);

    expect($result)->toBeTrue()
        ->and($client->calls[0]['args']['ContentType'])->toBe('text/plain');
});

it('appends content to existing S3 object', function () {
    $existingBody = fopen('php://memory', 'r+');
    fwrite($existingBody, 'Hello');
    rewind($existingBody);
    $mockBody = new Stream($existingBody);

    $client = MockS3Client::create([
        'getObject' => fn (array $args) => new Result(['Body' => $mockBody]),
        'putObject' => fn (array $args) => new Result([]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());
    $result = $filesystem->append('file.txt', ' World');

    expect($result)->toBeTrue();

    // Find the putObject call
    $putCall = null;

    foreach ($client->calls as $call) {
        if ($call['method'] === 'putObject') {
            $putCall = $call;
        }
    }

    expect($putCall)->not->toBeNull()
        ->and($putCall['args']['Body'])->toBe('Hello World');
});

it('appends content creating new file when it does not exist', function () {
    $client = MockS3Client::create([
        'getObject' => MockS3Client::createException('NoSuchKey', 404),
        'putObject' => fn (array $args) => new Result([]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());
    $result = $filesystem->append('new-file.txt', 'New content');

    expect($result)->toBeTrue();

    $putCall = null;

    foreach ($client->calls as $call) {
        if ($call['method'] === 'putObject') {
            $putCall = $call;
        }
    }

    expect($putCall)->not->toBeNull()
        ->and($putCall['args']['Body'])->toBe('New content');
});

it('deletes an object from S3', function () {
    $client = MockS3Client::create([
        'deleteObject' => fn (array $args) => new Result([]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());
    $result = $filesystem->delete('file-to-delete.txt');

    expect($result)->toBeTrue()
        ->and($client->calls[0]['method'])->toBe('deleteObject')
        ->and($client->calls[0]['args']['Bucket'])->toBe('test-bucket')
        ->and($client->calls[0]['args']['Key'])->toBe('file-to-delete.txt');
});

it('copies an object within the same bucket', function () {
    $client = MockS3Client::create([
        'copyObject' => fn (array $args) => new Result([]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());
    $result = $filesystem->copy('source.txt', 'destination.txt');

    expect($result)->toBeTrue()
        ->and($client->calls[0]['method'])->toBe('copyObject')
        ->and($client->calls[0]['args']['Bucket'])->toBe('test-bucket')
        ->and($client->calls[0]['args']['Key'])->toBe('destination.txt')
        ->and($client->calls[0]['args']['CopySource'])->toBe('test-bucket/source.txt');
});

it('moves an object by copying then deleting the source', function () {
    $client = MockS3Client::create([
        'copyObject' => fn (array $args) => new Result([]),
        'deleteObject' => fn (array $args) => new Result([]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());
    $result = $filesystem->move('source.txt', 'destination.txt');

    expect($result)->toBeTrue();

    $methods = array_column($client->calls, 'method');
    expect($methods)->toContain('copyObject')
        ->and($methods)->toContain('deleteObject');
});

it('creates a directory marker on S3', function () {
    $client = MockS3Client::create([
        'putObject' => fn (array $args) => new Result([]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());
    $result = $filesystem->makeDirectory('new-directory');

    expect($result)->toBeTrue()
        ->and($client->calls[0]['args']['Key'])->toBe('new-directory/')
        ->and($client->calls[0]['args']['Body'])->toBe('')
        ->and($client->calls[0]['args']['ContentType'])->toBe('application/x-directory');
});

it('deletes directory by listing and batch-deleting objects', function () {
    $client = MockS3Client::create([
        'listObjectsV2' => fn (array $args) => new Result([
            'Contents' => [
                ['Key' => 'dir/file1.txt'],
                ['Key' => 'dir/file2.txt'],
                ['Key' => 'dir/sub/file3.txt'],
            ],
        ]),
        'deleteObjects' => fn (array $args) => new Result([]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());
    $result = $filesystem->deleteDirectory('dir');

    expect($result)->toBeTrue();

    $deleteCall = null;

    foreach ($client->calls as $call) {
        if ($call['method'] === 'deleteObjects') {
            $deleteCall = $call;
        }
    }

    expect($deleteCall)->not->toBeNull()
        ->and($deleteCall['args']['Delete']['Objects'])->toHaveCount(3);
});

it('sets visibility to public using public-read ACL', function () {
    $client = MockS3Client::create([
        'putObjectAcl' => fn (array $args) => new Result([]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());
    $result = $filesystem->setVisibility('file.txt', 'public');

    expect($result)->toBeTrue()
        ->and($client->calls[0]['method'])->toBe('putObjectAcl')
        ->and($client->calls[0]['args']['ACL'])->toBe('public-read');
});

it('sets visibility to private using private ACL', function () {
    $client = MockS3Client::create([
        'putObjectAcl' => fn (array $args) => new Result([]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());
    $result = $filesystem->setVisibility('file.txt', 'private');

    expect($result)->toBeTrue()
        ->and($client->calls[0]['args']['ACL'])->toBe('private');
});

it('returns public visibility when AllUsers READ grant exists', function () {
    $client = MockS3Client::create([
        'getObjectAcl' => fn (array $args) => new Result([
            'Grants' => [
                [
                    'Grantee' => [
                        'URI' => 'http://acs.amazonaws.com/groups/global/AllUsers',
                        'Type' => 'Group',
                    ],
                    'Permission' => 'READ',
                ],
            ],
        ]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());

    expect($filesystem->visibility('file.txt'))->toBe('public');
});

it('returns private visibility when no public grants exist', function () {
    $client = MockS3Client::create([
        'getObjectAcl' => fn (array $args) => new Result([
            'Grants' => [
                [
                    'Grantee' => [
                        'ID' => 'owner-id',
                        'Type' => 'CanonicalUser',
                    ],
                    'Permission' => 'FULL_CONTROL',
                ],
            ],
        ]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());

    expect($filesystem->visibility('file.txt'))->toBe('private');
});

it('detects content types from file extensions', function () {
    $client = MockS3Client::create([
        'putObject' => fn (array $args) => new Result([]),
    ]);

    $filesystem = new S3Filesystem($client, MockS3Client::createConfig());

    $extensionTests = [
        'image.png' => 'image/png',
        'style.css' => 'text/css',
        'script.js' => 'application/javascript',
        'data.json' => 'application/json',
        'page.html' => 'text/html',
        'doc.pdf' => 'application/pdf',
        'archive.zip' => 'application/zip',
        'text.txt' => 'text/plain',
        'video.mp4' => 'video/mp4',
        'audio.mp3' => 'audio/mpeg',
        'font.woff2' => 'font/woff2',
        'image.svg' => 'image/svg+xml',
        'data.csv' => 'text/csv',
        'data.xml' => 'application/xml',
        'image.webp' => 'image/webp',
        'archive.tar' => 'application/x-tar',
        'archive.gz' => 'application/gzip',
        'font.woff' => 'font/woff',
        'unknown.xyz' => 'application/octet-stream',
    ];

    foreach ($extensionTests as $filename => $expectedType) {
        $client->calls = [];
        $filesystem->write($filename, 'test');

        expect($client->calls[0]['args']['ContentType'])
            ->toBe($expectedType, "Expected '$expectedType' for '$filename'");
    }
});
