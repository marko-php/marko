<?php

declare(strict_types=1);

use Marko\CodeIndexer\Attributes\AttributeParser;
use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\Config\ConfigScanner;
use Marko\CodeIndexer\Module\ModuleWalker;
use Marko\CodeIndexer\Translations\TranslationScanner;
use Marko\CodeIndexer\ValueObject\ModuleInfo;
use Marko\CodeIndexer\Views\TemplateScanner;
use Marko\Core\Path\ProjectPaths;
use Marko\Lsp\Features\AttributeFeature;
use Marko\Lsp\Features\CodeLensFeature;
use Marko\Lsp\Features\ConfigKeyFeature;
use Marko\Lsp\Features\TemplateFeature;
use Marko\Lsp\Features\TranslationFeature;
use Marko\Lsp\Protocol\LspProtocol;
use Marko\Lsp\Server\DocumentStore;
use Marko\Lsp\Server\LspServer;

function makeNullIndexCache(): IndexCache
{
    $emptyWalker = new class () extends ModuleWalker
    {
        public function __construct() {}

        public function walk(): array
        {
            return [];
        }
    };
    $emptyAttributeParser = new class () extends AttributeParser
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
    $emptyConfigScanner = new class () extends ConfigScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $emptyTemplateScanner = new class () extends TemplateScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $emptyTranslationScanner = new class () extends TranslationScanner
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

beforeEach(function () {
    $this->in = fopen('php://memory', 'w+');
    $this->out = fopen('php://memory', 'w+');
    $this->protocol = new LspProtocol($this->in, $this->out);
    $index = makeNullIndexCache();
    $this->server = new LspServer(
        protocol: $this->protocol,
        documents: new DocumentStore(),
        attributes: new AttributeFeature($index),
        codeLens: new CodeLensFeature($index),
        configKeys: new ConfigKeyFeature($index),
        templates: new TemplateFeature($index),
        translations: new TranslationFeature($index),
    );
});

function readResponse($out): array
{
    rewind($out);
    $raw = (string) stream_get_contents($out);
    $body = substr($raw, strpos($raw, "\r\n\r\n") + 4);

    return json_decode($body, true);
}

it('responds to initialize with server capabilities', function () {
    $this->protocol->handleMessage(
        json_encode(['jsonrpc' => '2.0', 'method' => 'initialize', 'params' => [], 'id' => 1]),
    );
    $response = readResponse($this->out);
    expect($response['result']['capabilities'])->toBeArray()
        ->and($response['result']['serverInfo'])->toBeArray();
});

it('declares completionProvider with trigger characters', function () {
    $this->protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'initialize', 'id' => 2]));
    $response = readResponse($this->out);
    expect($response['result']['capabilities']['completionProvider']['triggerCharacters'])
        ->toContain('"')
        ->toContain("'")
        ->toContain(':')
        ->toContain('.');
});

it('declares definitionProvider — handler routes via quotedStringAt to feature gotoDefinition()', function () {
    $this->protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'initialize', 'id' => 3]));
    $response = readResponse($this->out);
    expect($response['result']['capabilities']['definitionProvider'])->toBeTrue();
});

it('declares hoverProvider — handler routes via quotedStringAt to feature hover()', function () {
    $this->protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'initialize', 'id' => 4]));
    $response = readResponse($this->out);
    expect($response['result']['capabilities']['hoverProvider'])->toBeTrue();
});

it('declares codeLensProvider capability', function () {
    $this->protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'initialize', 'id' => 5]));
    $response = readResponse($this->out);
    expect($response['result']['capabilities']['codeLensProvider'])->toBeArray();
});

it('responds to shutdown with null and exits cleanly on exit notification', function () {
    $this->protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'shutdown', 'id' => 6]));
    expect($this->server->isShuttingDown())->toBeTrue();
    $this->protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'exit']));
    expect($this->protocol->isShutdown())->toBeTrue();
});

it('returns server info including name and version', function () {
    $this->protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'initialize', 'id' => 7]));
    $response = readResponse($this->out);
    expect($response['result']['serverInfo']['name'])->toBe('marko-lsp')
        ->and($response['result']['serverInfo']['version'])->toBeString();
});
