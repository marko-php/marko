<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools;

use Marko\CodeIndexer\Cache\IndexCache;

readonly class ValidateModuleTool implements ToolHandlerInterface
{
    public function __construct(private IndexCache $index) {}

    public static function definition(IndexCache $index): ToolDefinition
    {
        return new ToolDefinition(
            name: 'validate_module',
            description: 'Run consistency checks on a Marko module and return diagnostics',
            inputSchema: [
                'type' => 'object',
                'properties' => ['module' => ['type' => 'string']],
                'required' => ['module'],
            ],
            handler: new self($index),
        );
    }

    public function handle(array $arguments): array
    {
        $moduleName = (string) $arguments['module'];
        $module = null;

        foreach ($this->index->getModules() as $m) {
            if ($m->name === $moduleName) {
                $module = $m;
                break;
            }
        }

        if ($module === null) {
            return [
                'content' => [['type' => 'text', 'text' => "Module not found in index: $moduleName"]],
                'isError' => true,
            ];
        }

        $diagnostics = [];

        if (!is_file($module->path . '/composer.json')) {
            $diagnostics[] = [
                'severity' => 'error',
                'message' => 'composer.json not found',
                'file' => $module->path . '/composer.json',
                'line' => 0,
                'suggestion' => 'Add composer.json declaring marko-module type',
            ];
        }

        $prefix = $this->classPrefix($moduleName);
        $modulePlugins = array_filter(
            $this->index->getPlugins(),
            fn ($p) => str_starts_with($p->class, $prefix),
        );

        $bySortOrderTarget = [];

        foreach ($modulePlugins as $p) {
            if ($p->type !== 'Before') {
                continue;
            }

            $key = $p->target . '::' . $p->method . ':' . $p->sortOrder;
            $bySortOrderTarget[$key][] = $p;
        }

        foreach ($bySortOrderTarget as $group) {
            if (count($group) > 1) {
                foreach ($group as $p) {
                    $diagnostics[] = [
                        'severity' => 'warning',
                        'message' => "Duplicate Before sortOrder $p->sortOrder for $p->target::$p->method",
                        'file' => $module->path,
                        'line' => 0,
                        'suggestion' => 'Adjust sortOrder values to be unique per target method',
                    ];
                }
            }
        }

        $modulePrefs = array_filter(
            $this->index->getPreferences(),
            fn ($p) => $p->module === $moduleName,
        );

        foreach ($modulePrefs as $p) {
            if (!class_exists($p->implementation) && !interface_exists($p->implementation)) {
                $diagnostics[] = [
                    'severity' => 'error',
                    'message' => "Preference implementation does not exist: $p->implementation",
                    'file' => $module->path,
                    'line' => 0,
                    'suggestion' => 'Verify the class name spelling or ensure the file is autoloaded',
                ];
            }
        }

        if ($diagnostics === []) {
            return ['content' => [['type' => 'text', 'text' => "Module $moduleName: no issues found."]]];
        }

        $text = "Diagnostics for $moduleName:\n";

        foreach ($diagnostics as $d) {
            $text .= "- [{$d['severity']}] {$d['message']} ({$d['file']}:{$d['line']})\n  → {$d['suggestion']}\n";
        }

        return ['content' => [['type' => 'text', 'text' => $text]]];
    }

    private function classPrefix(string $moduleName): string
    {
        $parts = explode('/', $moduleName, 2);

        if (count($parts) !== 2) {
            return '';
        }

        $vendor = ucfirst($parts[0]);
        $name = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $parts[1])));

        return "$vendor\\$name\\";
    }
}
