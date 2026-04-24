<?php

declare(strict_types=1);

namespace Marko\CodeIndexer\Views;

use Marko\CodeIndexer\Contract\TemplateScannerInterface;
use Marko\CodeIndexer\ValueObject\ModuleInfo;
use Marko\CodeIndexer\ValueObject\TemplateEntry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class TemplateScanner implements TemplateScannerInterface
{
    /** @param list<string> $extensions */
    public function __construct(
        private readonly array $extensions = ['latte'],
    ) {}

    /** @return list<TemplateEntry> */
    public function scan(ModuleInfo $module): array
    {
        $viewsDir = $module->path . '/resources/views';

        if (!is_dir($viewsDir)) {
            return [];
        }

        $entries = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($viewsDir, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }

            $ext = $file->getExtension();

            if (!in_array($ext, $this->extensions, true)) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($viewsDir) + 1);
            $templateName = preg_replace('/\.' . preg_quote($ext, '/') . '$/', '', $relative);
            $templateName = str_replace(DIRECTORY_SEPARATOR, '/', $templateName);

            $entries[] = new TemplateEntry(
                moduleName: $module->name,
                templateName: $templateName,
                absolutePath: $file->getPathname(),
                extension: $ext,
            );
        }

        return $entries;
    }
}
