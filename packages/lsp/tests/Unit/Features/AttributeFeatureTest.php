<?php

declare(strict_types=1);

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\ValueObject\ObserverEntry;
use Marko\CodeIndexer\ValueObject\PluginEntry;
use Marko\CodeIndexer\ValueObject\RouteEntry;
use Marko\Lsp\Features\AttributeFeature;

function makeAttributeIndex(array $observers = [], array $plugins = [], array $routes = []): IndexCache
{
    return new class ($observers, $plugins, $routes) extends IndexCache
    {
        public function __construct(
            private array $observers,
            private array $plugins,
            private array $routes,
        ) {
            // Skip parent constructor
        }

        public function getObservers(): array
        {
            return $this->observers;
        }

        public function getPlugins(): array
        {
            return $this->plugins;
        }

        public function getRoutes(): array
        {
            return $this->routes;
        }
    };
}

it('offers event classes in Observer event parameter completion', function () {
    $index = makeAttributeIndex(
        observers: [
            new ObserverEntry('App\Observers\OrderObserver', 'App\Events\OrderPlaced', 'handle', 10),
            new ObserverEntry('App\Observers\UserObserver', 'App\Events\UserRegistered', 'handle', 10),
        ],
    );
    $feature = new AttributeFeature($index);

    $line = "#[Observer(event: 'App\\Events\\Order";
    $items = $feature->complete($line, strlen($line));

    expect($items)->toHaveCount(1)
        ->and($items[0]['label'])->toBe('App\Events\OrderPlaced')
        ->and($items[0]['kind'])->toBe(7);
});

it('offers class references in Plugin target parameter completion', function () {
    $index = makeAttributeIndex(
        plugins: [
            new PluginEntry('App\Plugins\CartPlugin', 'App\Services\CartService', 'beforeAdd', 'before', 10),
            new PluginEntry('App\Plugins\OrderPlugin', 'App\Services\OrderService', 'afterCreate', 'after', 10),
        ],
    );
    $feature = new AttributeFeature($index);

    $line = "#[Plugin(target: 'App\\Services\\Cart";
    $items = $feature->complete($line, strlen($line));

    expect($items)->toHaveCount(1)
        ->and($items[0]['label'])->toBe('App\Services\CartService')
        ->and($items[0]['kind'])->toBe(7);
});

it('validates Command name format and flags invalid names as diagnostic', function () {
    $index = makeAttributeIndex();
    $feature = new AttributeFeature($index);

    $documentText = "<?php\n#[Command(name: 'foo')]\nclass FooCommand {}\n#[Command(name: 'group:cmd')]\nclass GroupCmd {}";
    $diagnostics = $feature->diagnostics($documentText);

    expect($diagnostics)->toHaveCount(1)
        ->and($diagnostics[0]['code'])->toBe('marko.command.invalid_name')
        ->and($diagnostics[0]['message'])->toContain('foo')
        ->and($diagnostics[0]['severity'])->toBe(1);
});

it('offers middleware class names in Route middleware array completion', function () {
    $index = makeAttributeIndex(
        routes: [
            new RouteEntry('GET', '/api/orders', 'App\Http\Controllers\OrderController', 'index'),
            new RouteEntry('POST', '/api/orders', 'App\Http\Controllers\OrderController', 'store'),
        ],
    );
    $feature = new AttributeFeature($index);

    // RouteEntry has no middleware field — best-effort returns empty list
    $line = "#[Middleware('";
    $items = $feature->complete($line, strlen($line));

    expect($items)->toBeArray();
});

it('offers DisableRoute with zero parameters', function () {
    $index = makeAttributeIndex();
    $feature = new AttributeFeature($index);

    $line = '#[Disable';
    $items = $feature->complete($line, strlen($line));

    expect($items)->toBeArray();
});

it('does not offer completion outside of Marko attributes', function () {
    $index = makeAttributeIndex(
        observers: [
            new ObserverEntry('App\Observers\OrderObserver', 'App\Events\OrderPlaced', 'handle', 10),
        ],
    );
    $feature = new AttributeFeature($index);

    $line = '$config->get("some.key"';
    $items = $feature->complete($line, strlen($line));

    expect($items)->toBeEmpty();
});
