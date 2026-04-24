<?php

declare(strict_types=1);

namespace Marko\CodeIndexer\Attributes;

use FilesystemIterator;
use Marko\CodeIndexer\Contract\AttributeParserInterface;
use Marko\CodeIndexer\ValueObject\CommandEntry;
use Marko\CodeIndexer\ValueObject\ModuleInfo;
use Marko\CodeIndexer\ValueObject\ObserverEntry;
use Marko\CodeIndexer\ValueObject\PluginEntry;
use Marko\CodeIndexer\ValueObject\PreferenceEntry;
use Marko\CodeIndexer\ValueObject\RouteEntry;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class AttributeParser implements AttributeParserInterface
{
    private Parser $parser;

    /** @var list<array{file: string, error: string}> */
    private array $diagnostics = [];

    public function __construct()
    {
        $factory = new ParserFactory();
        $this->parser = $factory->createForHostVersion();
    }

    /** @return list<ObserverEntry> */
    public function observers(ModuleInfo $module): array
    {
        $entries = [];

        foreach ($this->phpFiles($module) as $file) {
            $ast = $this->parse($file);
            if ($ast === null) {
                continue;
            }

            foreach ($this->findClasses($ast) as $class) {
                if (!$this->classIsInNamespace($class, $module)) {
                    continue;
                }
                $className = $this->resolveClassName($class);
                foreach ($this->classAttributes($class) as $attr) {
                    if (!$this->attrNameMatches($attr, 'Marko\Core\Attributes\Observer')) {
                        continue;
                    }
                    $event = $this->getArgValueAsString($attr->args, 'event', 0) ?? '';
                    $sortOrder = $this->getArgValueAsInt($attr->args, 'priority', 1) ?? 0;
                    $entries[] = new ObserverEntry(
                        class: $className,
                        event: $event,
                        method: 'handle',
                        sortOrder: $sortOrder,
                    );
                }
            }
        }

        return $entries;
    }

    /** @return list<PluginEntry> */
    public function plugins(ModuleInfo $module): array
    {
        $entries = [];

        foreach ($this->phpFiles($module) as $file) {
            $ast = $this->parse($file);
            if ($ast === null) {
                continue;
            }

            foreach ($this->findClasses($ast) as $class) {
                if (!$this->classIsInNamespace($class, $module)) {
                    continue;
                }
                $className = $this->resolveClassName($class);
                $pluginTarget = null;

                foreach ($this->classAttributes($class) as $attr) {
                    if ($this->attrNameMatches($attr, 'Marko\Core\Attributes\Plugin')) {
                        $pluginTarget = $this->getArgValueAsString($attr->args, 'target', 0) ?? '';
                    }
                }

                if ($pluginTarget === null) {
                    continue;
                }

                foreach ($class->getMethods() as $method) {
                    foreach ($this->methodAttributes($method) as $attr) {
                        $type = null;
                        $sortOrder = 0;
                        $targetMethod = null;

                        if ($this->attrNameMatches($attr, 'Marko\Core\Attributes\Before')) {
                            $type = 'before';
                            $sortOrder = $this->getArgValueAsInt($attr->args, 'sortOrder', 0) ?? 0;
                            $targetMethod = $this->getArgValueAsString($attr->args, 'method', 1);
                        } elseif ($this->attrNameMatches($attr, 'Marko\Core\Attributes\After')) {
                            $type = 'after';
                            $sortOrder = $this->getArgValueAsInt($attr->args, 'sortOrder', 0) ?? 0;
                            $targetMethod = $this->getArgValueAsString($attr->args, 'method', 1);
                        }

                        if ($type !== null) {
                            $entries[] = new PluginEntry(
                                class: $className,
                                target: $pluginTarget,
                                method: $targetMethod ?? $method->name->toString(),
                                type: $type,
                                sortOrder: $sortOrder,
                            );
                        }
                    }
                }
            }
        }

        return $entries;
    }

    /** @return list<PreferenceEntry> */
    public function preferences(ModuleInfo $module): array
    {
        $entries = [];

        foreach ($this->phpFiles($module) as $file) {
            $ast = $this->parse($file);
            if ($ast === null) {
                continue;
            }

            foreach ($this->findClasses($ast) as $class) {
                if (!$this->classIsInNamespace($class, $module)) {
                    continue;
                }
                $className = $this->resolveClassName($class);
                foreach ($this->classAttributes($class) as $attr) {
                    if (!$this->attrNameMatches($attr, 'Marko\Core\Attributes\Preference')) {
                        continue;
                    }
                    $replaces = $this->getArgValueAsString($attr->args, 'replaces', 0) ?? '';
                    $entries[] = new PreferenceEntry(
                        interface: $replaces,
                        implementation: $className,
                        module: $module->name,
                    );
                }
            }
        }

        return $entries;
    }

    /** @return list<CommandEntry> */
    public function commands(ModuleInfo $module): array
    {
        $entries = [];

        foreach ($this->phpFiles($module) as $file) {
            $ast = $this->parse($file);
            if ($ast === null) {
                continue;
            }

            foreach ($this->findClasses($ast) as $class) {
                if (!$this->classIsInNamespace($class, $module)) {
                    continue;
                }
                $className = $this->resolveClassName($class);
                foreach ($this->classAttributes($class) as $attr) {
                    if (!$this->attrNameMatches($attr, 'Marko\Core\Attributes\Command')) {
                        continue;
                    }
                    $name = $this->getArgValueAsString($attr->args, 'name', 0) ?? '';
                    $description = $this->getArgValueAsString($attr->args, 'description', 1) ?? '';
                    $entries[] = new CommandEntry(
                        name: $name,
                        class: $className,
                        description: $description,
                    );
                }
            }
        }

        return $entries;
    }

    /** @return list<RouteEntry> */
    public function routes(ModuleInfo $module): array
    {
        $entries = [];

        $httpMethods = [
            'Marko\Routing\Attributes\Get' => 'GET',
            'Marko\Routing\Attributes\Post' => 'POST',
            'Marko\Routing\Attributes\Put' => 'PUT',
            'Marko\Routing\Attributes\Patch' => 'PATCH',
            'Marko\Routing\Attributes\Delete' => 'DELETE',
        ];

        foreach ($this->phpFiles($module) as $file) {
            $ast = $this->parse($file);
            if ($ast === null) {
                continue;
            }

            foreach ($this->findClasses($ast) as $class) {
                if (!$this->classIsInNamespace($class, $module)) {
                    continue;
                }
                $className = $this->resolveClassName($class);

                foreach ($class->getMethods() as $method) {
                    $methodName = $method->name->toString();
                    $isDisabled = false;

                    foreach ($this->methodAttributes($method) as $attr) {
                        if ($this->attrNameMatches($attr, 'Marko\Routing\Attributes\DisableRoute')) {
                            $isDisabled = true;
                            break;
                        }
                    }

                    if ($isDisabled) {
                        $entries[] = new RouteEntry(
                            method: 'DISABLED',
                            path: '',
                            class: $className,
                            action: $methodName,
                        );
                        continue;
                    }

                    foreach ($this->methodAttributes($method) as $attr) {
                        foreach ($httpMethods as $attrFqn => $httpVerb) {
                            if (!$this->attrNameMatches($attr, $attrFqn)) {
                                continue;
                            }
                            $path = $this->getArgValueAsString($attr->args, 'path', 0) ?? '';
                            $entries[] = new RouteEntry(
                                method: $httpVerb,
                                path: $path,
                                class: $className,
                                action: $methodName,
                            );
                        }
                    }
                }
            }
        }

        return $entries;
    }

    /** @return list<array{file: string, error: string}> */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /** @return list<string> */
    private function phpFiles(ModuleInfo $module): array
    {
        $srcDir = $module->path . '/src';
        if (!is_dir($srcDir)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS),
        );

        $files = [];
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /** @return list<Node>|null */
    private function parse(string $file): ?array
    {
        try {
            $code = file_get_contents($file);
            $ast = $this->parser->parse($code);
            if ($ast === null) {
                return null;
            }

            $traverser = new NodeTraverser();
            $traverser->addVisitor(new NameResolver());
            return $traverser->traverse($ast);
        } catch (Error $e) {
            $this->diagnostics[] = ['file' => $file, 'error' => $e->getMessage()];
            return null;
        }
    }

    /**
     * @param list<Node> $ast
     * @return list<Class_>
     */
    private function findClasses(array $ast): array
    {
        $classes = [];
        $this->walkNodes($ast, static function (Node $node) use (&$classes): void {
            if ($node instanceof Class_ && $node->name !== null) {
                $classes[] = $node;
            }
        });

        return $classes;
    }

    /**
     * @param list<Node> $nodes
     */
    private function walkNodes(array $nodes, callable $callback): void
    {
        foreach ($nodes as $node) {
            $callback($node);
            foreach ($node->getSubNodeNames() as $subName) {
                $sub = $node->$subName;
                if (is_array($sub)) {
                    $this->walkNodes($sub, $callback);
                } elseif ($sub instanceof Node) {
                    $this->walkNodes([$sub], $callback);
                }
            }
        }
    }

    private function resolveClassName(Class_ $class): string
    {
        if ($class->namespacedName !== null) {
            return $class->namespacedName->toString();
        }

        return $class->name?->toString() ?? '';
    }

    private function classIsInNamespace(Class_ $class, ModuleInfo $module): bool
    {
        $className = $this->resolveClassName($class);
        $ns = rtrim($module->namespace, '\\') . '\\';

        return str_starts_with($className, $ns);
    }

    /** @return list<Attribute> */
    private function classAttributes(Class_ $class): array
    {
        $attrs = [];
        foreach ($class->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                $attrs[] = $attr;
            }
        }

        return $attrs;
    }

    /** @return list<Attribute> */
    private function methodAttributes(Node\Stmt\ClassMethod $method): array
    {
        $attrs = [];
        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                $attrs[] = $attr;
            }
        }

        return $attrs;
    }

    private function attrNameMatches(Attribute $attr, string $fqn): bool
    {
        $name = $attr->name;
        if ($name instanceof Node\Name\FullyQualified || $name instanceof Node\Name) {
            return ltrim($name->toString(), '\\') === ltrim($fqn, '\\');
        }

        return false;
    }

    /**
     * @param Arg[] $args
     */
    private function getArgValueAsString(array $args, string $namedKey, int $positionalIndex): ?string
    {
        $value = $this->getArgValue($args, $namedKey, $positionalIndex);
        if ($value === null) {
            return null;
        }

        if ($value instanceof Node\Scalar\String_) {
            return $value->value;
        }

        if ($value instanceof Node\Expr\ClassConstFetch) {
            $class = $value->class;
            if ($class instanceof Node\Name) {
                return $class->toString();
            }
        }

        return null;
    }

    /**
     * @param Arg[] $args
     */
    private function getArgValueAsInt(array $args, string $namedKey, int $positionalIndex): ?int
    {
        $value = $this->getArgValue($args, $namedKey, $positionalIndex);
        if ($value === null) {
            return null;
        }

        if ($value instanceof Node\Scalar\Int_) {
            return $value->value;
        }

        return null;
    }

    /**
     * @param Arg[] $args
     */
    private function getArgValue(array $args, string $namedKey, int $positionalIndex): ?Node\Expr
    {
        // Try named arg first
        foreach ($args as $arg) {
            if ($arg->name !== null && $arg->name->toString() === $namedKey) {
                return $arg->value;
            }
        }

        // Fall back to positional
        $positional = array_values(array_filter($args, static fn ($a) => $a->name === null));

        return $positional[$positionalIndex]?->value ?? null;
    }
}
