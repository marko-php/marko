<?php

declare(strict_types=1);

namespace Marko\Core\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Exceptions\CommandException;
use Marko\Core\Module\ModuleManifest;
use ReflectionClass;

/**
 * Discovers command classes in module src directories.
 */
readonly class CommandDiscovery
{
    public function __construct(
        private ClassFileParser $classFileParser,
    ) {}

    /**
     * Discover commands from the given module manifests.
     *
     * @param array<ModuleManifest> $modules
     * @return array<CommandDefinition>
     * @throws CommandException When a command class is invalid
     */
    public function discover(
        array $modules,
    ): array {
        $commands = [];

        foreach ($modules as $manifest) {
            $srcDir = $manifest->path . '/src';

            if (!is_dir($srcDir)) {
                continue;
            }

            $commands = array_merge($commands, $this->discoverInDirectory($srcDir));
        }

        return $commands;
    }

    /**
     * @return array<CommandDefinition>
     * @throws CommandException
     */
    private function discoverInDirectory(
        string $directory,
    ): array {
        $commands = [];

        foreach ($this->classFileParser->findPhpFiles($directory) as $file) {
            $filePath = $file->getPathname();
            $className = $this->classFileParser->extractClassName($filePath);

            if ($className === null) {
                continue;
            }

            // Load the file so class is available for reflection
            require_once $filePath;

            if (!class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            $attributes = $reflection->getAttributes(Command::class);

            if (count($attributes) === 0) {
                continue;
            }

            // Validate that execute method exists
            if (!$reflection->hasMethod('execute')) {
                throw CommandException::missingExecuteMethod($className);
            }

            // Validate that class implements CommandInterface
            if (!$reflection->implementsInterface(CommandInterface::class)) {
                throw CommandException::doesNotImplementInterface($className);
            }

            $attribute = $attributes[0]->newInstance();
            $commands[] = new CommandDefinition(
                commandClass: $className,
                name: $attribute->name,
                description: $attribute->description,
            );
        }

        return $commands;
    }
}
