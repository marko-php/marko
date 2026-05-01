<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools;

use Marko\CodeIndexer\Cache\IndexCache;

readonly class ResolveTemplateTool implements ToolHandlerInterface
{
    public function __construct(private IndexCache $index) {}

    public static function definition(IndexCache $index): ToolDefinition
    {
        return new ToolDefinition(
            name: 'resolve_template',
            description: "Find the absolute file path for a template using 'module::template/path' syntax",
            inputSchema: [
                'type' => 'object',
                'properties' => ['template' => ['type' => 'string']],
                'required' => ['template'],
            ],
            handler: new self($index),
        );
    }

    public function handle(array $arguments): array
    {
        $template = (string) $arguments['template'];
        $parts = explode('::', $template, 2);

        if (count($parts) !== 2) {
            return [
                'content' => [['type' => 'text', 'text' => "Invalid template format. Expected 'module::template/path'."]],
                'isError' => true,
            ];
        }

        [$module, $name] = $parts;

        foreach ($this->index->getTemplates() as $t) {
            if ($t->moduleName === $module && $t->templateName === $name) {
                return ['content' => [['type' => 'text', 'text' => "Template found: $t->absolutePath"]]];
            }
        }

        $available = array_filter($this->index->getTemplates(), fn ($t) => $t->moduleName === $module);
        $names = array_map(fn ($t) => $t->templateName, $available);

        return [
            'content' => [['type' => 'text', 'text' => "Template not found: $template\nAvailable in module '$module': " . implode(', ', $names)]],
            'isError' => true,
        ];
    }
}
