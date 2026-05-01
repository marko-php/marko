<?php

declare(strict_types=1);

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\Contract\AttributeParserInterface;
use Marko\CodeIndexer\Contract\ConfigScannerInterface;
use Marko\CodeIndexer\Contract\ModuleWalkerInterface;
use Marko\CodeIndexer\Contract\TemplateScannerInterface;
use Marko\CodeIndexer\Contract\TranslationScannerInterface;
use Marko\CodeIndexer\ValueObject\ConfigKeyEntry;
use Marko\CodeIndexer\ValueObject\ModuleInfo;
use Marko\Core\Path\ProjectPaths;
use Marko\Lsp\Features\AttributeFeature;
use Marko\Lsp\Features\CodeLensFeature;
use Marko\Lsp\Features\ConfigKeyFeature;
use Marko\Lsp\Features\TemplateFeature;
use Marko\Lsp\Features\TranslationFeature;
use Marko\Lsp\Protocol\LspProtocol;
use Marko\Lsp\Server\DiagnosticsNotifier;
use Marko\Lsp\Server\DocumentStore;
use Marko\Lsp\Server\LspServer;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeDiagnosticsNullIndexCache(): IndexCache
{
    $emptyWalker = new class () implements ModuleWalkerInterface
    {
        public function walk(): array
        {
            return [];
        }
    };
    $emptyAttributeParser = new class () implements AttributeParserInterface
    {
        public function observers(ModuleInfo $m): array
        {
            return [];
        }

        public function plugins(ModuleInfo $m): array
        {
            return [];
        }

        public function preferences(ModuleInfo $m): array
        {
            return [];
        }

        public function commands(ModuleInfo $m): array
        {
            return [];
        }

        public function routes(ModuleInfo $m): array
        {
            return [];
        }
    };
    $emptyConfigScanner = new class () implements ConfigScannerInterface
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $emptyTemplateScanner = new class () implements TemplateScannerInterface
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $emptyTranslationScanner = new class () implements TranslationScannerInterface
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };

    return new IndexCache(
        new ProjectPaths(sys_get_temp_dir()),
        $emptyWalker,
        $emptyAttributeParser,
        $emptyConfigScanner,
        $emptyTemplateScanner,
        $emptyTranslationScanner,
    );
}

function makeIndexCacheWithConfigKey(string $key): IndexCache
{
    $stubModule = new ModuleInfo(name: 'test', path: '/test', namespace: 'Test');
    $walker = new class ($stubModule) implements ModuleWalkerInterface
    {
        public function __construct(private ModuleInfo $module) {}

        public function walk(): array
        {
            return [$this->module];
        }
    };
    $emptyAttributeParser = new class () implements AttributeParserInterface
    {
        public function observers(ModuleInfo $m): array
        {
            return [];
        }

        public function plugins(ModuleInfo $m): array
        {
            return [];
        }

        public function preferences(ModuleInfo $m): array
        {
            return [];
        }

        public function commands(ModuleInfo $m): array
        {
            return [];
        }

        public function routes(ModuleInfo $m): array
        {
            return [];
        }
    };
    $configScanner = new class ($key) implements ConfigScannerInterface
    {
        public function __construct(private string $configKey) {}

        public function scan(ModuleInfo $m): array
        {
            return [new ConfigKeyEntry(
                key: $this->configKey,
                type: 'string',
                defaultValue: null,
                module: 'test',
                file: '/test/config.php',
                line: 1,
            )];
        }
    };
    $emptyTemplateScanner = new class () implements TemplateScannerInterface
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $emptyTranslationScanner = new class () implements TranslationScannerInterface
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };

    // Use a unique temp dir so IndexCache can build its cache file
    $tmpDir = sys_get_temp_dir() . '/lsp-test-index-' . uniqid();
    mkdir($tmpDir, 0755, true);

    $cache = new IndexCache(
        new ProjectPaths($tmpDir),
        $walker,
        $emptyAttributeParser,
        $configScanner,
        $emptyTemplateScanner,
        $emptyTranslationScanner,
    );
    $cache->build();

    return $cache;
}

/** @return array<int, array<string, mixed>> All publishDiagnostics notifications captured from the output stream */
function capturePublishedDiagnostics(mixed $out): array
{
    rewind($out);
    $raw = (string) stream_get_contents($out);
    $notifications = [];
    $offset = 0;

    while (($pos = strpos($raw, 'Content-Length:', $offset)) !== false) {
        $headerEnd = strpos($raw, "\r\n\r\n", $pos);
        if ($headerEnd === false) {
            break;
        }
        $header = substr($raw, $pos, $headerEnd - $pos);
        preg_match('/Content-Length:\s*(\d+)/i', $header, $m);
        $length = (int) ($m[1] ?? 0);
        $bodyStart = $headerEnd + 4;
        $body = substr($raw, $bodyStart, $length);
        $decoded = json_decode($body, true);
        if (is_array($decoded) && ($decoded['method'] ?? '') === 'textDocument/publishDiagnostics') {
            $notifications[] = $decoded;
        }
        $offset = $bodyStart + $length;
    }

    return $notifications;
}

function makeServerWithNotifier(IndexCache $index): array
{
    $in = fopen('php://memory', 'w+');
    $out = fopen('php://memory', 'w+');
    $protocol = new LspProtocol($in, $out);
    $notifier = new DiagnosticsNotifier($protocol);
    $server = new LspServer(
        protocol: $protocol,
        documents: new DocumentStore(),
        attributes: new AttributeFeature($index),
        codeLens: new CodeLensFeature($index),
        configKeys: new ConfigKeyFeature($index),
        templates: new TemplateFeature($index),
        translations: new TranslationFeature($index),
        notifier: $notifier,
    );

    return [$server, $protocol, $out];
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('publishes diagnostics on textDocument/didOpen when ConfigKeyFeature finds unknown keys', function (): void {
    // Index has no known keys, so any config() call is "unknown"
    $index = makeDiagnosticsNullIndexCache();
    [$server, $protocol, $out] = makeServerWithNotifier($index);

    $didOpen = json_encode([
        'jsonrpc' => '2.0',
        'method' => 'textDocument/didOpen',
        'params' => [
            'textDocument' => [
                'uri' => 'file:///app/Test.php',
                'text' => "<?php\n\$val = \$config->get('not.a.real.key');\n",
            ],
        ],
    ]);

    $protocol->handleMessage($didOpen);

    $notifications = capturePublishedDiagnostics($out);
    expect($notifications)->not->toBeEmpty()
        ->and($notifications[0]['params']['uri'])->toBe('file:///app/Test.php')
        ->and($notifications[0]['params']['diagnostics'])->not->toBeEmpty();
});

it('publishes diagnostics on textDocument/didChange when document is modified', function (): void {
    $index = makeDiagnosticsNullIndexCache();
    [$server, $protocol, $out] = makeServerWithNotifier($index);

    // First open with clean content
    $protocol->handleMessage(json_encode([
        'jsonrpc' => '2.0',
        'method' => 'textDocument/didOpen',
        'params' => [
            'textDocument' => [
                'uri' => 'file:///app/Test.php',
                'text' => "<?php\n",
            ],
        ],
    ]));

    // Clear and rewind to only capture change notifications
    rewind($out);
    ftruncate($out, 0);

    // Now change with content that has an unknown key
    $protocol->handleMessage(json_encode([
        'jsonrpc' => '2.0',
        'method' => 'textDocument/didChange',
        'params' => [
            'textDocument' => ['uri' => 'file:///app/Test.php'],
            'contentChanges' => [['text' => "<?php\n\$config->get('missing.key');\n"]],
        ],
    ]));

    $notifications = capturePublishedDiagnostics($out);
    expect($notifications)->not->toBeEmpty()
        ->and($notifications[0]['params']['uri'])->toBe('file:///app/Test.php')
        ->and($notifications[0]['params']['diagnostics'])->not->toBeEmpty();
});

it('aggregates diagnostics from multiple features into one notification', function (): void {
    // Empty index means both ConfigKeyFeature and TranslationFeature will flag unknowns
    $index = makeDiagnosticsNullIndexCache();
    [$server, $protocol, $out] = makeServerWithNotifier($index);

    // Text that triggers both config and translation diagnostics
    $text = "<?php\n\$config->get('bad.config.key');\n\$trans->get('bad.translation.key');\n";

    $protocol->handleMessage(json_encode([
        'jsonrpc' => '2.0',
        'method' => 'textDocument/didOpen',
        'params' => [
            'textDocument' => [
                'uri' => 'file:///app/Multi.php',
                'text' => $text,
            ],
        ],
    ]));

    $notifications = capturePublishedDiagnostics($out);
    expect($notifications)->toHaveCount(1);
    expect(count($notifications[0]['params']['diagnostics']))->toBeGreaterThanOrEqual(2);
});

it('publishes empty diagnostics array when no issues are found (clears prior diagnostics)', function (): void {
    // Use a document with no config() or translation calls — no features fire
    $index = makeDiagnosticsNullIndexCache();
    [$server, $protocol, $out] = makeServerWithNotifier($index);

    $protocol->handleMessage(json_encode([
        'jsonrpc' => '2.0',
        'method' => 'textDocument/didOpen',
        'params' => [
            'textDocument' => [
                'uri' => 'file:///app/Clean.php',
                'text' => "<?php\n// no config or translation calls here\n\$x = 1 + 1;\n",
            ],
        ],
    ]));

    $notifications = capturePublishedDiagnostics($out);
    expect($notifications)->not->toBeEmpty()
        ->and($notifications[0]['params']['diagnostics'])->toBe([]);
});

it('sets source to "marko-lsp" on every emitted diagnostic', function (): void {
    $index = makeDiagnosticsNullIndexCache();
    [$server, $protocol, $out] = makeServerWithNotifier($index);

    $protocol->handleMessage(json_encode([
        'jsonrpc' => '2.0',
        'method' => 'textDocument/didOpen',
        'params' => [
            'textDocument' => [
                'uri' => 'file:///app/Test.php',
                'text' => "<?php\n\$config->get('unknown.key');\n",
            ],
        ],
    ]));

    $notifications = capturePublishedDiagnostics($out);
    expect($notifications)->not->toBeEmpty();

    foreach ($notifications[0]['params']['diagnostics'] as $diag) {
        expect($diag['source'])->toBe('marko-lsp');
    }
});
