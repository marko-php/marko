<?php

declare(strict_types=1);

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\ValueObject\ObserverEntry;
use Marko\CodeIndexer\ValueObject\PluginEntry;
use Marko\Lsp\Features\CodeLensFeature;

function makeCodeLensIndex(array $observers = [], array $plugins = []): IndexCache
{
    return new class ($observers, $plugins) extends IndexCache
    {
        public function __construct(
            private array $observers,
            private array $plugins,
        ) {
            // Skip parent constructor
        }

        public function findObserversForEvent(string $eventClass): array
        {
            return array_values(
                array_filter($this->observers, fn (ObserverEntry $o) => $o->event === $eventClass),
            );
        }

        public function findPluginsForTarget(string $targetClass): array
        {
            return array_values(
                array_filter($this->plugins, fn (PluginEntry $p) => $p->target === $targetClass),
            );
        }
    };
}

function firstLensWithCommand(array $lenses, string $command): ?array
{
    return array_values(array_filter($lenses, fn ($l) => $l['command']['command'] === $command))[0] ?? null;
}

it('publishes a code lens above every event class indicating observer count', function () {
    $index = makeCodeLensIndex(
        observers: [
            new ObserverEntry('App\Observers\SendWelcomeEmail', 'App\Events\UserCreated', 'handle', 10),
            new ObserverEntry('App\Observers\LogUserCreated', 'App\Events\UserCreated', 'handle', 20),
        ],
    );
    $feature = new CodeLensFeature($index);

    $doc = "<?php\nnamespace App\\Events;\nclass UserCreated {}";
    $lenses = $feature->lenses($doc);

    $observerLens = firstLensWithCommand($lenses, 'marko.showObservers');

    expect($observerLens)->not->toBeNull()
        ->and($observerLens['command']['title'])->toBe('2 observers listen')
        ->and($observerLens['command']['arguments'])->toBe(['App\Events\UserCreated'])
        ->and($observerLens['range']['start']['line'])->toBe(2);
});

it('publishes a code lens above every target class indicating plugin count', function () {
    $index = makeCodeLensIndex(
        plugins: [
            new PluginEntry('App\Plugins\LogPlugin', 'App\Services\OrderService', 'beforeCreate', 'before', 10),
        ],
    );
    $feature = new CodeLensFeature($index);

    $doc = "<?php\nnamespace App\\Services;\nclass OrderService {}";
    $lenses = $feature->lenses($doc);

    $pluginLens = firstLensWithCommand($lenses, 'marko.showPlugins');

    expect($pluginLens)->not->toBeNull()
        ->and($pluginLens['command']['title'])->toBe('1 plugin intercept')
        ->and($pluginLens['command']['arguments'])->toBe(['App\Services\OrderService'])
        ->and($pluginLens['range']['start']['line'])->toBe(2);
});

it('shows zero count lenses when nothing listens or intercepts', function () {
    $index = makeCodeLensIndex();
    $feature = new CodeLensFeature($index);

    $doc = "<?php\nnamespace App\\Events;\nclass SomethingHappened {}";
    $lenses = $feature->lenses($doc);

    $observerLens = firstLensWithCommand($lenses, 'marko.showObservers');
    $pluginLens = firstLensWithCommand($lenses, 'marko.showPlugins');

    expect($observerLens['command']['title'])->toBe('0 observers listen')
        ->and($pluginLens['command']['title'])->toBe('0 plugins intercept');
});

it('resolves lens click to a list of observer or plugin locations', function () {
    $index = makeCodeLensIndex(
        observers: [
            new ObserverEntry('App\Observers\SendWelcomeEmail', 'App\Events\UserCreated', 'handle', 10),
        ],
        plugins: [
            new PluginEntry('App\Plugins\LogPlugin', 'App\Services\OrderService', 'beforeCreate', 'before', 5),
        ],
    );
    $feature = new CodeLensFeature($index);

    $observerLocations = $feature->resolveObservers('App\Events\UserCreated');
    $pluginLocations = $feature->resolvePlugins('App\Services\OrderService');

    expect($observerLocations)->toHaveCount(1)
        ->and($observerLocations[0]['uri'])->toBe('class://App\Observers\SendWelcomeEmail')
        ->and($observerLocations[0]['label'])->toBe('App\Observers\SendWelcomeEmail::handle (sortOrder: 10)')
        ->and($observerLocations[0]['range']['start']['line'])->toBe(0);

    expect($pluginLocations)->toHaveCount(1)
        ->and($pluginLocations[0]['uri'])->toBe('class://App\Plugins\LogPlugin')
        ->and($pluginLocations[0]['label'])->toBe('App\Plugins\LogPlugin::beforeCreate [before, sortOrder: 5]')
        ->and($pluginLocations[0]['range']['start']['line'])->toBe(0);
});

it('refreshes lenses when the workspace re-indexes', function () {
    $observers = [
        new ObserverEntry('App\Observers\SendWelcomeEmail', 'App\Events\UserCreated', 'handle', 10),
    ];

    $index = new class ($observers) extends IndexCache
    {
        private array $currentObservers;

        public function __construct(array $initialObservers)
        {
            $this->currentObservers = $initialObservers;
        }

        public function findObserversForEvent(string $eventClass): array
        {
            return array_values(
                array_filter($this->currentObservers, fn (ObserverEntry $o) => $o->event === $eventClass),
            );
        }

        public function findPluginsForTarget(string $targetClass): array
        {
            return [];
        }

        public function setObservers(array $observers): void
        {
            $this->currentObservers = $observers;
        }
    };

    $feature = new CodeLensFeature($index);
    $doc = "<?php\nnamespace App\\Events;\nclass UserCreated {}";

    $lensesBefore = $feature->lenses($doc);
    $observerLensBefore = firstLensWithCommand($lensesBefore, 'marko.showObservers');
    expect($observerLensBefore['command']['title'])->toBe('1 observer listen');

    // Simulate re-index: add another observer
    $index->setObservers([
        new ObserverEntry('App\Observers\SendWelcomeEmail', 'App\Events\UserCreated', 'handle', 10),
        new ObserverEntry('App\Observers\AuditLog', 'App\Events\UserCreated', 'handle', 20),
    ]);

    $lensesAfter = $feature->lenses($doc);
    $observerLensAfter = firstLensWithCommand($lensesAfter, 'marko.showObservers');
    expect($observerLensAfter['command']['title'])->toBe('2 observers listen');
});
