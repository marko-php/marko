<?php

declare(strict_types=1);

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\ValueObject\ObserverEntry;
use Marko\Mcp\Protocol\JsonRpcProtocol;
use Marko\Mcp\Server\McpServer;
use Marko\Mcp\Tools\FindEventObserversTool;

function makeFakeIndexCacheForObservers(array $observers = []): IndexCache
{
    return new class ($observers) extends IndexCache
    {
        public function __construct(private array $obs)
        {
            // Skip parent constructor
        }

        public function findObserversForEvent(string $eventClass): array
        {
            return array_values(
                array_filter($this->obs, fn (ObserverEntry $o) => $o->event === $eventClass),
            );
        }
    };
}

it('registers find_event_observers tool', function (): void {
    $in = fopen('php://memory', 'w+');
    $out = fopen('php://memory', 'w+');
    $protocol = new JsonRpcProtocol($in, $out);
    $server = new McpServer($protocol);

    $tool = FindEventObserversTool::definition(makeFakeIndexCacheForObservers());
    $server->registerTool($tool);

    $protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'tools/list', 'id' => 1]));
    rewind($out);
    $response = json_decode((string) stream_get_contents($out), true);

    $names = array_column($response['result']['tools'], 'name');
    expect($names)->toContain('find_event_observers');
});

it('returns all observer classes listening to a given event class', function (): void {
    $observer = new ObserverEntry(
        class: 'App\Observers\OrderObserver',
        event: 'App\Events\OrderPlaced',
        method: 'handle',
        sortOrder: 10,
    );
    $index = makeFakeIndexCacheForObservers([$observer]);
    $tool = FindEventObserversTool::definition($index);

    $result = $tool->handler->handle(['event' => 'App\Events\OrderPlaced']);

    expect($result['content'][0]['text'])->toContain('App\Observers\OrderObserver');
});

it('includes observer priority in results', function (): void {
    $observer = new ObserverEntry(
        class: 'App\Observers\OrderObserver',
        event: 'App\Events\OrderPlaced',
        method: 'handle',
        sortOrder: 42,
    );
    $index = makeFakeIndexCacheForObservers([$observer]);
    $tool = FindEventObserversTool::definition($index);

    $result = $tool->handler->handle(['event' => 'App\Events\OrderPlaced']);

    expect($result['content'][0]['text'])->toContain('42');
});

it('returns empty list for events with no observers', function (): void {
    $index = makeFakeIndexCacheForObservers([]);
    $tool = FindEventObserversTool::definition($index);

    $result = $tool->handler->handle(['event' => 'App\Events\NonExistentEvent']);

    expect($result['content'][0]['text'])->toContain('No observers found for event');
});
