<?php

declare(strict_types=1);

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\ValueObject\PluginEntry;
use Marko\Mcp\Protocol\JsonRpcProtocol;
use Marko\Mcp\Server\McpServer;
use Marko\Mcp\Tools\FindPluginsTargetingTool;

function makeFakeIndexCacheForPlugins(array $plugins = []): IndexCache
{
    return new class ($plugins) extends IndexCache
    {
        public function __construct(private array $plugs)
        {
            // Skip parent constructor
        }

        public function findPluginsForTarget(string $targetClass): array
        {
            return array_values(
                array_filter($this->plugs, fn (PluginEntry $p) => $p->target === $targetClass),
            );
        }
    };
}

it('registers find_plugins_targeting tool', function (): void {
    $in = fopen('php://memory', 'w+');
    $out = fopen('php://memory', 'w+');
    $protocol = new JsonRpcProtocol($in, $out);
    $server = new McpServer($protocol);

    $tool = FindPluginsTargetingTool::definition(makeFakeIndexCacheForPlugins());
    $server->registerTool($tool);

    $protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'tools/list', 'id' => 1]));
    rewind($out);
    $response = json_decode((string) stream_get_contents($out), true);

    $names = array_column($response['result']['tools'], 'name');
    expect($names)->toContain('find_plugins_targeting');
});

it('returns all plugin classes targeting a given class with their Before/After methods and sortOrders', function (): void {
    $plugin = new PluginEntry(
        class: 'App\Plugins\OrderPlugin',
        target: 'App\Services\OrderService',
        method: 'beforePlace',
        type: 'before',
        sortOrder: 10,
    );
    $index = makeFakeIndexCacheForPlugins([$plugin]);
    $tool = FindPluginsTargetingTool::definition($index);

    $result = $tool->handler->handle(['target' => 'App\Services\OrderService']);

    expect($result['content'][0]['text'])->toContain('App\Plugins\OrderPlugin');
    expect($result['content'][0]['text'])->toContain('beforePlace');
    expect($result['content'][0]['text'])->toContain('before');
    expect($result['content'][0]['text'])->toContain('10');
});

it('returns empty list for classes with no plugins', function (): void {
    $index = makeFakeIndexCacheForPlugins([]);
    $tool = FindPluginsTargetingTool::definition($index);

    $result = $tool->handler->handle(['target' => 'App\Services\NonExistentService']);

    expect($result['content'][0]['text'])->toContain('No plugins found for');
});
