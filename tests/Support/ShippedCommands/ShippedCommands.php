<?php

declare(strict_types=1);

namespace Marko\Tests\Support\ShippedCommands;

use FilesystemIterator;
use Marko\Core\Attributes\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/**
 * Finds every #[Command] class shipped in packages/*\/src.
 */
readonly class ShippedCommands
{
    /**
     * Every shipped command, keyed by command name.
     *
     * @return array<string, array{class: class-string, attribute: Command, path: string}>
     */
    public static function all(): array
    {
        $commands = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(dirname(__DIR__, 3) . '/packages', FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            $path = $file->getPathname();

            if (!str_ends_with($path, '.php') || !preg_match('#/packages/[^/]+/src/#', $path)) {
                continue;
            }

            $source = (string) file_get_contents($path);

            if (!str_contains($source, '#[Command(')
                || !preg_match('/^namespace\s+([^;]+);/m', $source, $namespace)
                || !preg_match('/^(?:readonly\s+|abstract\s+)*class\s+(\w+)/m', $source, $class)
            ) {
                continue;
            }

            /** @var class-string $className */
            $className = $namespace[1] . '\\' . $class[1];
            $attributes = new ReflectionClass($className)->getAttributes(Command::class);

            if ($attributes === []) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();
            $commands[$attribute->name] = ['class' => $className, 'attribute' => $attribute, 'path' => $path];
        }

        ksort($commands);

        return $commands;
    }
}
