<?php

declare(strict_types=1);

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\ValueObject\PreferenceEntry;
use Marko\Mcp\Protocol\JsonRpcProtocol;
use Marko\Mcp\Server\McpServer;
use Marko\Mcp\Tools\ResolvePreferenceTool;

function makeFakeIndexCacheForPreference(array $preferences = []): IndexCache
{
    return new class ($preferences) extends IndexCache
    {
        public function __construct(private array $prefs)
        {
            // Skip parent constructor
        }

        public function getPreferences(): array
        {
            return $this->prefs;
        }

        public function getTemplates(): array
        {
            return [];
        }
    };
}

it('registers resolve_preference tool', function (): void {
    $in = fopen('php://memory', 'w+');
    $out = fopen('php://memory', 'w+');
    $protocol = new JsonRpcProtocol($in, $out);
    $server = new McpServer($protocol);

    $tool = ResolvePreferenceTool::definition(makeFakeIndexCacheForPreference());
    $server->registerTool($tool);

    $protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'tools/list', 'id' => 1]));
    rewind($out);
    $response = json_decode((string) stream_get_contents($out), true);

    $names = array_column($response['result']['tools'], 'name');
    expect($names)->toContain('resolve_preference');
});

it('resolves an interface to its bound implementation when a preference exists', function (): void {
    $pref = new PreferenceEntry(
        interface: 'App\Contracts\FooInterface',
        implementation: 'App\Models\Foo',
        module: 'App\Module',
    );
    $index = makeFakeIndexCacheForPreference([$pref]);
    $tool = ResolvePreferenceTool::definition($index);

    $result = $tool->handler->handle(['class' => 'App\Contracts\FooInterface']);

    expect($result['content'][0]['text'])->toContain('App\Models\Foo');
});

it('resolves a class to its #[Preference]-annotated replacement when one exists', function (): void {
    $pref = new PreferenceEntry(
        interface: 'App\Services\OriginalService',
        implementation: 'App\Services\ExtendedService',
        module: 'App\ExtensionModule',
    );
    $index = makeFakeIndexCacheForPreference([$pref]);
    $tool = ResolvePreferenceTool::definition($index);

    $result = $tool->handler->handle(['class' => 'App\Services\OriginalService']);

    expect($result['content'][0]['text'])->toContain('App\Services\ExtendedService');
    expect($result['content'][0]['text'])->toContain('App\ExtensionModule');
});

it('returns null when no preference or binding exists', function (): void {
    $index = makeFakeIndexCacheForPreference([]);
    $tool = ResolvePreferenceTool::definition($index);

    $result = $tool->handler->handle(['class' => 'App\Contracts\NonExistentInterface']);

    expect($result['content'][0]['text'])->toContain('No preference found for');
});
