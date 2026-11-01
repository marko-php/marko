<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Core\Path\ProjectPaths;
use Marko\Debugbar\Storage\DebugbarStorage;

function makeStorage(?string $basePath = null): DebugbarStorage
{
    $path = $basePath ?? sys_get_temp_dir().'/marko-debugbar-storage-test-'.bin2hex(random_bytes(4));

    $config = new ConfigRepository([
        'debugbar' => [
            'storage' => [
                'path' => $path.'/storage/debugbar',
                'max_files' => 100,
            ],
        ],
    ]);

    return new DebugbarStorage($config, new ProjectPaths($path));
}

function makeDataset(string $id): array
{
    return [
        'id' => $id,
        'stored_at' => '2026-01-01T00:00:00+00:00',
        'profiler_url' => '/_debugbar/'.$id,
        'summary' => ['time' => 42.5, 'memory' => 16],
        'collectors' => [
            'messages' => ['messages' => array_fill(0, 100, ['message' => 'hello', 'level' => 'debug'])],
        ],
    ];
}

it('lists stored datasets without fully decoding every dataset file', function (): void {
    $storage = makeStorage();

    $id = str_pad('a', 12, 'a');
    $storage->put(makeDataset($id));

    // Corrupt the full dataset file so get() would return null
    $dir = $storage->directory();
    file_put_contents($dir.'/'.$id.'.json', 'CORRUPTED', LOCK_EX);

    // all() must still return the entry by reading the summary index, not the full file
    $items = $storage->all();

    expect($items)->toHaveCount(1)
        ->and($items[0]['id'])->toBe($id);
});

it('returns the same summary fields for each listed dataset', function (): void {
    $storage = makeStorage();

    $id = str_pad('b', 12, 'b');
    $storage->put(makeDataset($id));

    $items = $storage->all();

    expect($items)->toHaveCount(1)
        ->and($items[0])->toHaveKey('id')
        ->and($items[0])->toHaveKey('stored_at')
        ->and($items[0])->toHaveKey('profiler_url')
        ->and($items[0])->toHaveKey('summary')
        ->and($items[0])->toHaveKey('mtime')
        ->and($items[0]['id'])->toBe($id)
        ->and($items[0]['stored_at'])->toBe('2026-01-01T00:00:00+00:00')
        ->and($items[0]['profiler_url'])->toBe('/_debugbar/'.$id)
        ->and($items[0]['summary'])->toBe(['time' => 42.5, 'memory' => 16])
        ->and($items[0]['mtime'])->toBeInt();
});

it('orders listed datasets by most-recent first', function (): void {
    $storage = makeStorage();

    $idA = str_pad('c', 12, 'c');
    $idB = str_pad('d', 12, 'd');

    $storage->put(makeDataset($idA));
    sleep(1);
    $storage->put(makeDataset($idB));

    $items = $storage->all();

    expect($items)->toHaveCount(2)
        ->and($items[0]['id'])->toBe($idB)
        ->and($items[1]['id'])->toBe($idA);
});

it('writes profiles readable by the owner only inside an owner-only directory', function (): void {
    $previousUmask = umask(0002);

    try {
        $storage = makeStorage();
        $id = str_pad('e', 12, 'e');
        $storage->put(makeDataset($id));
    } finally {
        umask($previousUmask);
    }

    $directory = $storage->directory();
    clearstatcache();

    expect(fileperms($directory) & 0777)->toBe(0700)
        ->and(fileperms($directory.'/'.$id.'.json') & 0777)->toBe(0600)
        ->and(fileperms($directory.'/'.$id.'.summary.json') & 0777)->toBe(0600);
});

it('uses Windows drive and UNC storage paths unchanged', function (string $path): void {
    $config = new ConfigRepository(['debugbar' => ['storage' => ['path' => $path]]]);
    $storage = new DebugbarStorage($config, new ProjectPaths('/srv/app'));

    expect($storage->directory())->toBe($path);
})->with([
    'windows drive with backslash' => 'C:\\app\\storage\\debugbar',
    'windows drive with forward slash' => 'C:/app/storage/debugbar',
    'unc share' => '\\\\server\\share\\debugbar',
]);

it('resolves a relative storage path against the project base path', function (): void {
    $config = new ConfigRepository(['debugbar' => ['storage' => ['path' => 'storage/debugbar/']]]);
    $storage = new DebugbarStorage($config, new ProjectPaths('/srv/app'));

    expect($storage->directory())->toBe('/srv/app/storage/debugbar');
});
