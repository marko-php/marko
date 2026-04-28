<?php

declare(strict_types=1);

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\ValueObject\CommandEntry;
use Marko\CodeIndexer\ValueObject\ModuleInfo;
use Marko\CodeIndexer\ValueObject\RouteEntry;
use Marko\Mcp\Tools\ListCommandsTool;
use Marko\Mcp\Tools\ListModulesTool;
use Marko\Mcp\Tools\ListRoutesTool;

function makeMcpStubCache(array $modules = [], array $commands = [], array $routes = []): IndexCache
{
    return new class ($modules, $commands, $routes) extends IndexCache
    {
        public function __construct(
            private array $stubbedModules,
            private array $stubbedCommands,
            private array $stubbedRoutes,
        ) {
            // Skip parent constructor — no dependencies needed
        }

        public function getModules(): array
        {
            return $this->stubbedModules;
        }

        public function getCommands(): array
        {
            return $this->stubbedCommands;
        }

        public function getRoutes(): array
        {
            return $this->stubbedRoutes;
        }
    };
}

// ──────────────────────────────────────────────────────────────────────────────
// ListModulesTool
// ──────────────────────────────────────────────────────────────────────────────

it('registers list_modules tool returning IndexCache::getModules output', function (): void {
    $cache = makeMcpStubCache(modules: [
        new ModuleInfo(name: 'Marko_Core', path: '/path/to/core', namespace: 'Marko\\Core'),
        new ModuleInfo(name: 'Marko_Auth', path: '/path/to/auth', namespace: 'Marko\\Auth'),
    ]);

    $definition = ListModulesTool::definition($cache);

    expect($definition->name)->toBe('list_modules');

    $result = $definition->handler->handle([]);
    $text = $result['content'][0]['text'];

    expect($text)->toContain('Marko_Core')
        ->and($text)->toContain('/path/to/core')
        ->and($text)->toContain('Marko_Auth')
        ->and($text)->toContain('/path/to/auth');
});

// ──────────────────────────────────────────────────────────────────────────────
// ListCommandsTool
// ──────────────────────────────────────────────────────────────────────────────

it('registers list_commands tool returning all CommandEntry records with name, class, module', function (): void {
    $cache = makeMcpStubCache(commands: [
        new CommandEntry(name: 'cache:clear', class: 'Marko\\Cache\\Command\\ClearCommand', description: 'Clears cache'),
        new CommandEntry(name: 'module:list', class: 'Marko\\Core\\Command\\ModuleListCommand', description: 'Lists modules'),
    ]);

    $definition = ListCommandsTool::definition($cache);

    expect($definition->name)->toBe('list_commands');

    $result = $definition->handler->handle([]);
    $text = $result['content'][0]['text'];

    expect($text)->toContain('cache:clear')
        ->and($text)->toContain('Marko\\Cache\\Command\\ClearCommand')
        ->and($text)->toContain('module:list')
        ->and($text)->toContain('Marko\\Core\\Command\\ModuleListCommand');
});

// ──────────────────────────────────────────────────────────────────────────────
// ListRoutesTool
// ──────────────────────────────────────────────────────────────────────────────

it('registers list_routes tool returning all RouteEntry records with method, path, handler', function (): void {
    $cache = makeMcpStubCache(routes: [
        new RouteEntry(method: 'GET', path: '/api/users', class: 'Marko\\Api\\Controller\\UserController', action: 'index'),
        new RouteEntry(method: 'POST', path: '/api/users', class: 'Marko\\Api\\Controller\\UserController', action: 'store'),
    ]);

    $definition = ListRoutesTool::definition($cache);

    expect($definition->name)->toBe('list_routes');

    $result = $definition->handler->handle([]);
    $text = $result['content'][0]['text'];

    expect($text)->toContain('GET')
        ->and($text)->toContain('/api/users')
        ->and($text)->toContain('Marko\\Api\\Controller\\UserController')
        ->and($text)->toContain('index')
        ->and($text)->toContain('POST')
        ->and($text)->toContain('store');
});

// ──────────────────────────────────────────────────────────────────────────────
// Filter support
// ──────────────────────────────────────────────────────────────────────────────

it('supports optional substring filter on each tool', function (): void {
    $cache = makeMcpStubCache(
        modules: [
            new ModuleInfo(name: 'Marko_Core', path: '/path/to/core', namespace: 'Marko\\Core'),
            new ModuleInfo(name: 'Marko_Auth', path: '/path/to/auth', namespace: 'Marko\\Auth'),
        ],
        commands: [
            new CommandEntry(name: 'cache:clear', class: 'Marko\\Cache\\Command\\ClearCommand', description: 'Clears cache'),
            new CommandEntry(name: 'module:list', class: 'Marko\\Core\\Command\\ModuleListCommand', description: 'Lists modules'),
        ],
        routes: [
            new RouteEntry(method: 'GET', path: '/api/users', class: 'Marko\\Api\\Controller\\UserController', action: 'index'),
            new RouteEntry(method: 'POST', path: '/api/orders', class: 'Marko\\Api\\Controller\\OrderController', action: 'store'),
        ],
    );

    // Modules filter
    $moduleResult = ListModulesTool::definition($cache)->handler->handle(['filter' => 'Auth']);
    $moduleText = $moduleResult['content'][0]['text'];
    expect($moduleText)->toContain('Marko_Auth')
        ->and($moduleText)->not->toContain('Marko_Core');

    // Commands filter
    $cmdResult = ListCommandsTool::definition($cache)->handler->handle(['filter' => 'cache']);
    $cmdText = $cmdResult['content'][0]['text'];
    expect($cmdText)->toContain('cache:clear')
        ->and($cmdText)->not->toContain('module:list');

    // Routes filter
    $routeResult = ListRoutesTool::definition($cache)->handler->handle(['filter' => 'orders']);
    $routeText = $routeResult['content'][0]['text'];
    expect($routeText)->toContain('/api/orders')
        ->and($routeText)->not->toContain('/api/users');
});

// ──────────────────────────────────────────────────────────────────────────────
// Empty filter match
// ──────────────────────────────────────────────────────────────────────────────

it('returns empty arrays when filter matches nothing', function (): void {
    $cache = makeMcpStubCache(
        modules: [
            new ModuleInfo(name: 'Marko_Core', path: '/path/to/core', namespace: 'Marko\\Core'),
        ],
        commands: [
            new CommandEntry(name: 'cache:clear', class: 'Marko\\Cache\\Command\\ClearCommand', description: 'Clears'),
        ],
        routes: [
            new RouteEntry(method: 'GET', path: '/api/users', class: 'Marko\\Api\\Controller\\UserController', action: 'index'),
        ],
    );

    $moduleText = ListModulesTool::definition($cache)->handler->handle(['filter' => 'nonexistent'])['content'][0]['text'];
    $cmdText = ListCommandsTool::definition($cache)->handler->handle(['filter' => 'nonexistent'])['content'][0]['text'];
    $routeText = ListRoutesTool::definition($cache)->handler->handle(['filter' => 'nonexistent'])['content'][0]['text'];

    expect($moduleText)->toBe('(no modules found)')
        ->and($cmdText)->toBe('(no commands found)')
        ->and($routeText)->toBe('(no routes found)');
});

// ──────────────────────────────────────────────────────────────────────────────
// Source file paths
// ──────────────────────────────────────────────────────────────────────────────

it('includes source file paths so the agent can open them', function (): void {
    $cache = makeMcpStubCache(
        modules: [
            new ModuleInfo(name: 'Marko_Core', path: '/var/www/packages/core', namespace: 'Marko\\Core'),
        ],
        commands: [
            new CommandEntry(name: 'cache:clear', class: 'Marko\\Cache\\Command\\ClearCommand', description: 'Clears'),
        ],
        routes: [
            new RouteEntry(method: 'GET', path: '/api/users', class: 'Marko\\Api\\Controller\\UserController', action: 'index'),
        ],
    );

    // Modules include the path (the module source directory)
    $moduleText = ListModulesTool::definition($cache)->handler->handle([])['content'][0]['text'];
    expect($moduleText)->toContain('/var/www/packages/core');

    // Commands include the class name (enables IDE/agent navigation)
    $cmdText = ListCommandsTool::definition($cache)->handler->handle([])['content'][0]['text'];
    expect($cmdText)->toContain('Marko\\Cache\\Command\\ClearCommand');

    // Routes include the class name
    $routeText = ListRoutesTool::definition($cache)->handler->handle([])['content'][0]['text'];
    expect($routeText)->toContain('Marko\\Api\\Controller\\UserController');
});
