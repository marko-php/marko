<?php

declare(strict_types=1);

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\ValueObject\ModuleInfo;
use Marko\CodeIndexer\ValueObject\PluginEntry;
use Marko\CodeIndexer\ValueObject\PreferenceEntry;
use Marko\Mcp\Protocol\JsonRpcProtocol;
use Marko\Mcp\Server\McpServer;
use Marko\Mcp\Tools\ValidateModuleTool;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function makeValidateIndexCache(
    array $modules = [],
    array $plugins = [],
    array $preferences = [],
): IndexCache {
    return new class ($modules, $plugins, $preferences) extends IndexCache
    {
        public function __construct(
            private array $stubbedModules,
            private array $stubbedPlugins,
            private array $stubbedPreferences,
        ) {
            // Skip parent constructor
        }

        public function getModules(): array
        {
            return $this->stubbedModules;
        }

        public function getPlugins(): array
        {
            return $this->stubbedPlugins;
        }

        public function getPreferences(): array
        {
            return $this->stubbedPreferences;
        }
    };
}

// ──────────────────────────────────────────────────────────────────────────────
// Requirement 1: registers validate_module tool
// ──────────────────────────────────────────────────────────────────────────────

it('registers validate_module tool', function (): void {
    $in = fopen('php://memory', 'w+');
    $out = fopen('php://memory', 'w+');
    $protocol = new JsonRpcProtocol($in, $out);
    $server = new McpServer($protocol);

    $tool = ValidateModuleTool::definition(makeValidateIndexCache());
    $server->registerTool($tool);

    $protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'tools/list', 'id' => 1]));
    rewind($out);
    $response = json_decode((string) stream_get_contents($out), true);

    $names = array_column($response['result']['tools'], 'name');
    expect($names)->toContain('validate_module');
});

// ──────────────────────────────────────────────────────────────────────────────
// Requirement 2: flags missing composer.json
// ──────────────────────────────────────────────────────────────────────────────

it('flags missing composer.json in target module', function (): void {
    $tmpDir = sys_get_temp_dir() . '/validate_module_test_' . uniqid();
    mkdir($tmpDir, 0755, true);
    // Intentionally do NOT create composer.json

    $module = new ModuleInfo(name: 'marko/test-module', path: $tmpDir, namespace: 'Marko\\TestModule');
    $index = makeValidateIndexCache(modules: [$module]);
    $tool = ValidateModuleTool::definition($index);

    $result = $tool->handler->handle(['module' => 'marko/test-module']);
    $text = $result['content'][0]['text'];

    expect($text)->toContain('composer.json');

    rmdir($tmpDir);
});

// ──────────────────────────────────────────────────────────────────────────────
// Requirement 3: flags duplicate Before plugin sortOrders targeting same method
// ──────────────────────────────────────────────────────────────────────────────

it('flags duplicate Before plugin sortOrders targeting the same method', function (): void {
    $tmpDir = sys_get_temp_dir() . '/validate_module_test_' . uniqid();
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir . '/composer.json', '{}');

    $module = new ModuleInfo(name: 'marko/my-module', path: $tmpDir, namespace: 'Marko\\MyModule');

    $plugin1 = new PluginEntry(
        class: 'Marko\\MyModule\\Plugin\\FooPlugin',
        target: 'Marko\\Core\\Service\\FooService',
        method: 'execute',
        type: 'Before',
        sortOrder: 10,
    );
    $plugin2 = new PluginEntry(
        class: 'Marko\\MyModule\\Plugin\\BarPlugin',
        target: 'Marko\\Core\\Service\\FooService',
        method: 'execute',
        type: 'Before',
        sortOrder: 10,
    );

    $index = makeValidateIndexCache(modules: [$module], plugins: [$plugin1, $plugin2]);
    $tool = ValidateModuleTool::definition($index);

    $result = $tool->handler->handle(['module' => 'marko/my-module']);
    $text = $result['content'][0]['text'];

    expect($text)->toContain('Duplicate Before sortOrder');
    expect($text)->toContain('10');

    unlink($tmpDir . '/composer.json');
    rmdir($tmpDir);
});

// ──────────────────────────────────────────────────────────────────────────────
// Requirement 4: flags Preferences pointing to non-existent classes
// ──────────────────────────────────────────────────────────────────────────────

it('flags Preferences pointing to non-existent classes', function (): void {
    $tmpDir = sys_get_temp_dir() . '/validate_module_test_' . uniqid();
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir . '/composer.json', '{}');

    $module = new ModuleInfo(name: 'marko/my-module', path: $tmpDir, namespace: 'Marko\\MyModule');

    $pref = new PreferenceEntry(
        interface: 'Some\\Interface',
        implementation: 'NonExistent\\Class\\ThatDoesNotExist',
        module: 'marko/my-module',
    );

    $index = makeValidateIndexCache(modules: [$module], preferences: [$pref]);
    $tool = ValidateModuleTool::definition($index);

    $result = $tool->handler->handle(['module' => 'marko/my-module']);
    $text = $result['content'][0]['text'];

    expect($text)->toContain('NonExistent\\Class\\ThatDoesNotExist');

    unlink($tmpDir . '/composer.json');
    rmdir($tmpDir);
});

// ──────────────────────────────────────────────────────────────────────────────
// Requirement 5: returns empty diagnostics for valid module
// ──────────────────────────────────────────────────────────────────────────────

it('returns empty diagnostics for a valid module', function (): void {
    $tmpDir = sys_get_temp_dir() . '/validate_module_test_' . uniqid();
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir . '/composer.json', '{}');

    $module = new ModuleInfo(name: 'marko/clean-module', path: $tmpDir, namespace: 'Marko\\CleanModule');
    $index = makeValidateIndexCache(modules: [$module]);
    $tool = ValidateModuleTool::definition($index);

    $result = $tool->handler->handle(['module' => 'marko/clean-module']);
    $text = $result['content'][0]['text'];

    expect($text)->toContain('no issues found');

    unlink($tmpDir . '/composer.json');
    rmdir($tmpDir);
});

// ──────────────────────────────────────────────────────────────────────────────
// Requirement 6: includes file and line for every finding
// ──────────────────────────────────────────────────────────────────────────────

it('includes file and line for every finding', function (): void {
    $tmpDir = sys_get_temp_dir() . '/validate_module_test_' . uniqid();
    mkdir($tmpDir, 0755, true);
    // No composer.json → triggers a finding

    $module = new ModuleInfo(name: 'marko/test-module', path: $tmpDir, namespace: 'Marko\\TestModule');
    $index = makeValidateIndexCache(modules: [$module]);
    $tool = ValidateModuleTool::definition($index);

    $result = $tool->handler->handle(['module' => 'marko/test-module']);
    $text = $result['content'][0]['text'];

    // Output must contain a file path and a line reference (colon-separated, e.g. /path/to/file:0)
    expect($text)->toMatch('/[^\s]+:\d+/');

    rmdir($tmpDir);
});

// ──────────────────────────────────────────────────────────────────────────────
// Requirement 7: suggests a fix for each diagnostic
// ──────────────────────────────────────────────────────────────────────────────

it('suggests a fix for each diagnostic', function (): void {
    $tmpDir = sys_get_temp_dir() . '/validate_module_test_' . uniqid();
    mkdir($tmpDir, 0755, true);
    // No composer.json → triggers a finding

    $module = new ModuleInfo(name: 'marko/test-module', path: $tmpDir, namespace: 'Marko\\TestModule');
    $index = makeValidateIndexCache(modules: [$module]);
    $tool = ValidateModuleTool::definition($index);

    $result = $tool->handler->handle(['module' => 'marko/test-module']);
    $text = $result['content'][0]['text'];

    // The suggestion arrow must appear
    expect($text)->toContain('→');

    rmdir($tmpDir);
});
