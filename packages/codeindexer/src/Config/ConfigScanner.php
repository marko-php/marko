<?php

declare(strict_types=1);

namespace Marko\CodeIndexer\Config;

use Marko\CodeIndexer\Contract\ConfigScannerInterface;
use Marko\CodeIndexer\ValueObject\ConfigKeyEntry;
use Marko\CodeIndexer\ValueObject\ModuleInfo;
use PhpParser\Error;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class ConfigScanner implements ConfigScannerInterface
{
    private Parser $parser;

    /** @var list<array{file: string, error: string}> */
    private array $diagnostics = [];

    public function __construct()
    {
        $factory = new ParserFactory();
        $this->parser = $factory->createForHostVersion();
    }

    /** @return list<ConfigKeyEntry> */
    public function scan(ModuleInfo $module): array
    {
        $configDir = $module->path . '/config';
        if (!is_dir($configDir)) {
            return [];
        }

        $entries = [];
        foreach (glob($configDir . '/*.php') ?: [] as $file) {
            $entries = [...$entries, ...$this->scanFile($file, $module->name)];
        }

        return $entries;
    }

    /** @return list<array{file: string, error: string}> */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    /** @return list<ConfigKeyEntry> */
    private function scanFile(string $file, string $moduleName): array
    {
        $code = file_get_contents($file);
        if ($code === false) {
            return [];
        }

        try {
            $stmts = $this->parser->parse($code);
        } catch (Error $e) {
            $this->diagnostics[] = ['file' => $file, 'error' => $e->getMessage()];

            return [];
        }

        if ($stmts === null) {
            return [];
        }

        $prefix = basename($file, '.php');

        foreach ($stmts as $stmt) {
            if (!$stmt instanceof Return_) {
                continue;
            }

            $expr = $stmt->expr;

            if (!$expr instanceof Array_) {
                $this->diagnostics[] = [
                    'file' => $file,
                    'error' => 'Dynamic config: return value is not a static array',
                ];

                return [];
            }

            return $this->flattenArray($expr, $prefix, $file, $moduleName);
        }

        return [];
    }

    /** @return list<ConfigKeyEntry> */
    private function flattenArray(Array_ $array, string $prefix, string $file, string $moduleName): array
    {
        $entries = [];

        foreach ($array->items as $item) {
            if (!$item instanceof ArrayItem) {
                continue;
            }

            $keyNode = $item->key;
            if ($keyNode instanceof String_) {
                $keyStr = $keyNode->value;
            } elseif ($keyNode instanceof Int_) {
                $keyStr = (string) $keyNode->value;
            } else {
                continue;
            }

            $fullKey = $prefix . '.' . $keyStr;
            $valueNode = $item->value;

            if ($valueNode instanceof String_) {
                $entries[] = new ConfigKeyEntry(
                    key: $fullKey,
                    type: 'string',
                    defaultValue: $valueNode->value,
                    module: $moduleName,
                    file: $file,
                    line: $valueNode->getLine(),
                );
            } elseif ($valueNode instanceof Int_) {
                $entries[] = new ConfigKeyEntry(
                    key: $fullKey,
                    type: 'int',
                    defaultValue: $valueNode->value,
                    module: $moduleName,
                    file: $file,
                    line: $valueNode->getLine(),
                );
            } elseif ($valueNode instanceof Float_) {
                $entries[] = new ConfigKeyEntry(
                    key: $fullKey,
                    type: 'float',
                    defaultValue: $valueNode->value,
                    module: $moduleName,
                    file: $file,
                    line: $valueNode->getLine(),
                );
            } elseif ($valueNode instanceof ConstFetch) {
                $name = strtolower($valueNode->name->toString());
                if ($name === 'true') {
                    $entries[] = new ConfigKeyEntry(
                        key: $fullKey,
                        type: 'bool',
                        defaultValue: true,
                        module: $moduleName,
                        file: $file,
                        line: $valueNode->getLine(),
                    );
                } elseif ($name === 'false') {
                    $entries[] = new ConfigKeyEntry(
                        key: $fullKey,
                        type: 'bool',
                        defaultValue: false,
                        module: $moduleName,
                        file: $file,
                        line: $valueNode->getLine(),
                    );
                } elseif ($name === 'null') {
                    $entries[] = new ConfigKeyEntry(
                        key: $fullKey,
                        type: 'null',
                        defaultValue: null,
                        module: $moduleName,
                        file: $file,
                        line: $valueNode->getLine(),
                    );
                } else {
                    $entries[] = new ConfigKeyEntry(
                        key: $fullKey,
                        type: 'dynamic',
                        defaultValue: null,
                        module: $moduleName,
                        file: $file,
                        line: $valueNode->getLine(),
                    );
                }
            } elseif ($valueNode instanceof Array_) {
                $entries = [...$entries, ...$this->flattenArray($valueNode, $fullKey, $file, $moduleName)];
            } else {
                $entries[] = new ConfigKeyEntry(
                    key: $fullKey,
                    type: 'dynamic',
                    defaultValue: null,
                    module: $moduleName,
                    file: $file,
                    line: $valueNode->getLine(),
                );
            }
        }

        return $entries;
    }
}
