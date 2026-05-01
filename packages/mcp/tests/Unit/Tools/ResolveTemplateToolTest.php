<?php

declare(strict_types=1);

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\ValueObject\TemplateEntry;
use Marko\Mcp\Protocol\JsonRpcProtocol;
use Marko\Mcp\Server\McpServer;
use Marko\Mcp\Tools\ResolveTemplateTool;

function makeFakeIndexCacheForTemplate(array $templates = []): IndexCache
{
    return new class ($templates) extends IndexCache
    {
        public function __construct(private array $tpls)
        {
            // Skip parent constructor
        }

        public function getPreferences(): array
        {
            return [];
        }

        public function getTemplates(): array
        {
            return $this->tpls;
        }
    };
}

it('registers resolve_template tool', function (): void {
    $in = fopen('php://memory', 'w+');
    $out = fopen('php://memory', 'w+');
    $protocol = new JsonRpcProtocol($in, $out);
    $server = new McpServer($protocol);

    $tool = ResolveTemplateTool::definition(makeFakeIndexCacheForTemplate());
    $server->registerTool($tool);

    $protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'tools/list', 'id' => 1]));
    rewind($out);
    $response = json_decode((string) stream_get_contents($out), true);

    $names = array_column($response['result']['tools'], 'name');
    expect($names)->toContain('resolve_template');
});

it('returns the absolute file path for a valid module::template', function (): void {
    $entry = new TemplateEntry(
        moduleName: 'App\Catalog',
        templateName: 'product/view',
        absolutePath: '/var/www/modules/catalog/resources/views/product/view.blade.php',
        extension: 'blade.php',
    );
    $index = makeFakeIndexCacheForTemplate([$entry]);
    $tool = ResolveTemplateTool::definition($index);

    $result = $tool->handler->handle(['template' => 'App\Catalog::product/view']);

    expect($result['content'][0]['text'])->toContain('/var/www/modules/catalog/resources/views/product/view.blade.php');
    expect($result)->not->toHaveKey('isError');
});

it('returns a structured not-found error including searched paths', function (): void {
    $entry = new TemplateEntry(
        moduleName: 'App\Catalog',
        templateName: 'product/list',
        absolutePath: '/var/www/modules/catalog/resources/views/product/list.blade.php',
        extension: 'blade.php',
    );
    $index = makeFakeIndexCacheForTemplate([$entry]);
    $tool = ResolveTemplateTool::definition($index);

    $result = $tool->handler->handle(['template' => 'App\Catalog::product/view']);

    expect($result)->toHaveKey('isError');
    expect($result['isError'])->toBeTrue();
    expect($result['content'][0]['text'])->toContain('Template not found');
    expect($result['content'][0]['text'])->toContain('product/list');
});
