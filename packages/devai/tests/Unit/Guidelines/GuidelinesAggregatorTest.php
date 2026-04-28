<?php

declare(strict_types=1);

use Marko\CodeIndexer\Contract\ModuleWalkerInterface;
use Marko\CodeIndexer\ValueObject\ModuleInfo;
use Marko\DevAi\Guidelines\GuidelinesAggregator;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/devai-aggregator-' . uniqid();
    mkdir($this->tempDir, 0755, true);

    // Create devai resources structure
    $devaiResources = $this->tempDir . '/devai/resources/ai/guidelines';
    mkdir($devaiResources, 0755, true);
    file_put_contents($devaiResources . '/core.md', "# Core\n\nCore guidelines.");
    $this->devaiRoot = $this->tempDir . '/devai';

    // Create some module fixtures
    $this->modAPath = $this->tempDir . '/modA';
    mkdir($this->modAPath . '/resources/ai', 0755, true);
    file_put_contents($this->modAPath . '/resources/ai/guidelines.md', "# Module A\n\nA-specific guidelines.");

    $this->modBPath = $this->tempDir . '/modB';
    mkdir($this->modBPath . '/resources/ai', 0755, true);
    file_put_contents($this->modBPath . '/resources/ai/guidelines.md', "# Module B\n\nB-specific guidelines.");

    $this->modCPath = $this->tempDir . '/modC';
    mkdir($this->modCPath, 0755, true); // No guidelines file
});

afterEach(function () {
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iter as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($this->tempDir);
});

function makeGuidelinesWalker(array $modules): ModuleWalkerInterface
{
    return new class ($modules) implements ModuleWalkerInterface
    {
        public function __construct(private array $m) {}

        public function walk(): array
        {
            return $this->m;
        }
    };
}

function makeGuidelinesModule(string $name, string $path): ModuleInfo
{
    return new ModuleInfo(name: $name, path: $path, namespace: '');
}

it('discovers resources/ai/guidelines.md across every installed module', function () {
    $walker = makeGuidelinesWalker([
        makeGuidelinesModule('marko/mod-a', $this->modAPath),
        makeGuidelinesModule('marko/mod-b', $this->modBPath),
    ]);
    $aggregator = new GuidelinesAggregator($walker, $this->devaiRoot);
    $result = $aggregator->aggregate();
    expect($result)->toHaveKey('marko/mod-a')
        ->and($result)->toHaveKey('marko/mod-b');
});

it('aggregates content preserving source package attribution', function () {
    $walker = makeGuidelinesWalker([
        makeGuidelinesModule('marko/mod-a', $this->modAPath),
    ]);
    $aggregator = new GuidelinesAggregator($walker, $this->devaiRoot);
    $result = $aggregator->aggregate();
    expect($result['marko/mod-a'])->toBe("# Module A\n\nA-specific guidelines.");
});

it('merges sections with consistent heading hierarchy', function () {
    $walker = makeGuidelinesWalker([
        makeGuidelinesModule('marko/mod-a', $this->modAPath),
        makeGuidelinesModule('marko/mod-b', $this->modBPath),
    ]);
    $aggregator = new GuidelinesAggregator($walker, $this->devaiRoot);
    $result = $aggregator->aggregate();
    expect($result['marko/mod-a'])->toContain('# Module A')
        ->and($result['marko/mod-b'])->toContain('# Module B');
});

it('includes Marko core framework guidelines from devai\'s own resources', function () {
    $walker = makeGuidelinesWalker([]);
    $aggregator = new GuidelinesAggregator($walker, $this->devaiRoot);
    $result = $aggregator->aggregate();
    expect($result)->toHaveKey('marko/core')
        ->and($result['marko/core'])->toBe("# Core\n\nCore guidelines.");
});

it('returns empty sections when no package contributes guidelines', function () {
    $noGuidelinesRoot = $this->tempDir . '/no-devai';
    mkdir($noGuidelinesRoot, 0755, true);
    $walker = makeGuidelinesWalker([
        makeGuidelinesModule('marko/mod-c', $this->modCPath),
    ]);
    $aggregator = new GuidelinesAggregator($walker, $noGuidelinesRoot);
    $result = $aggregator->aggregate();
    expect($result)->toBe([]);
});

it('produces deterministic ordering across repeated runs', function () {
    $walker = makeGuidelinesWalker([
        makeGuidelinesModule('marko/mod-b', $this->modBPath),
        makeGuidelinesModule('marko/mod-a', $this->modAPath),
    ]);
    $aggregator = new GuidelinesAggregator($walker, $this->devaiRoot);
    $result1 = $aggregator->aggregate();
    $result2 = $aggregator->aggregate();
    expect(array_keys($result1))->toBe(array_keys($result2))
        ->and(array_keys($result1))->toBe(['marko/core', 'marko/mod-a', 'marko/mod-b']);
});
