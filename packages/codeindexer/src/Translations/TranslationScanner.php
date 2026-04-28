<?php

declare(strict_types=1);

namespace Marko\CodeIndexer\Translations;

use Marko\CodeIndexer\Contract\TranslationScannerInterface;
use Marko\CodeIndexer\ValueObject\ModuleInfo;
use Marko\CodeIndexer\ValueObject\TranslationEntry;
use PhpParser\Error;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class TranslationScanner implements TranslationScannerInterface
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForHostVersion();
    }

    /** @return list<TranslationEntry> */
    public function scan(ModuleInfo $module): array
    {
        $translationsDir = $module->path . '/resources/translations';
        if (!is_dir($translationsDir)) {
            return [];
        }

        $entries = [];
        foreach (glob($translationsDir . '/*', GLOB_ONLYDIR) ?: [] as $localeDir) {
            $locale = basename($localeDir);
            foreach (glob($localeDir . '/*.php') ?: [] as $file) {
                $group = basename($file, '.php');
                $entries = [...$entries, ...$this->scanFile($file, $group, $locale, $module->name)];
            }
        }

        return $entries;
    }

    /** @return list<TranslationEntry> */
    private function scanFile(string $file, string $group, string $locale, string $moduleName): array
    {
        $code = file_get_contents($file);
        if ($code === false) {
            return [];
        }

        try {
            $stmts = $this->parser->parse($code);
        } catch (Error) {
            return [];
        }

        if ($stmts === null) {
            return [];
        }

        foreach ($stmts as $stmt) {
            if (!$stmt instanceof Return_) {
                continue;
            }

            if (!$stmt->expr instanceof Array_) {
                return [];
            }

            return $this->flattenArray($stmt->expr, '', $group, $locale, $moduleName, $file);
        }

        return [];
    }

    /** @return list<TranslationEntry> */
    private function flattenArray(Array_ $array, string $prefix, string $group, string $locale, string $moduleName, string $file): array
    {
        $entries = [];

        foreach ($array->items as $item) {
            if (!$item instanceof ArrayItem) {
                continue;
            }

            $keyNode = $item->key;
            if (!$keyNode instanceof String_) {
                continue;
            }

            $keyStr = $keyNode->value;
            $fullKey = $prefix === '' ? $keyStr : $prefix . '.' . $keyStr;

            if ($item->value instanceof Array_) {
                $entries = [...$entries, ...$this->flattenArray($item->value, $fullKey, $group, $locale, $moduleName, $file)];
            } else {
                $entries[] = new TranslationEntry(
                    key: $group . '.' . $fullKey,
                    group: $group,
                    locale: $locale,
                    namespace: $moduleName,
                    file: $file,
                    line: $item->getStartLine(),
                    module: $moduleName,
                );
            }
        }

        return $entries;
    }
}
