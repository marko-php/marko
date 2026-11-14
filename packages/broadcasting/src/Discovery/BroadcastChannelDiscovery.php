<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Discovery;

use Marko\Broadcasting\Attributes\BroadcastChannel;
use Marko\Broadcasting\ChannelAuthorizerInterface;
use Marko\Broadcasting\ChannelDefinition;
use Marko\Broadcasting\Exceptions\ChannelAuthorizationException;
use Marko\Broadcasting\PresenceChannelAuthorizerInterface;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Module\ModuleRepositoryInterface;
use ReflectionClass;
use ReflectionException;

/**
 * Discovers #[BroadcastChannel] authorizers in every module's src/ directory.
 */
readonly class BroadcastChannelDiscovery
{
    public function __construct(
        private ModuleRepositoryInterface $moduleRepository,
        private ClassFileParser $classFileParser,
    ) {}

    /**
     * @return list<ChannelDefinition>
     * @throws ChannelAuthorizationException|ReflectionException
     */
    public function discover(): array
    {
        /** @var array<string, ChannelDefinition> $definitions */
        $definitions = [];

        foreach ($this->moduleRepository->all() as $module) {
            $srcPath = $module->path . '/src';

            if (!is_dir($srcPath)) {
                continue;
            }

            foreach ($this->classFileParser->findPhpFiles($srcPath) as $file) {
                $definition = $this->discoverFromFile($file->getPathname());

                if ($definition === null) {
                    continue;
                }

                $existing = $definitions[$definition->pattern] ?? null;

                if (
                    $existing !== null
                    && $existing->authorizerClass !== $definition->authorizerClass
                ) {
                    throw ChannelAuthorizationException::duplicatePattern(
                        $definition->pattern,
                        [$existing->authorizerClass, $definition->authorizerClass],
                    );
                }

                $definitions[$definition->pattern] = $definition;
            }
        }

        return array_values($definitions);
    }

    /**
     * @throws ChannelAuthorizationException|ReflectionException
     */
    private function discoverFromFile(
        string $filePath,
    ): ?ChannelDefinition {
        $className = $this->classFileParser->extractClassName($filePath);

        if (
            $className === null
            || !$this->classFileParser->loadClass($filePath, $className)
        ) {
            return null;
        }

        $reflection = new ReflectionClass($className);
        $attributes = $reflection->getAttributes(BroadcastChannel::class);

        if ($attributes === []) {
            return null;
        }

        if (
            !$reflection->implementsInterface(ChannelAuthorizerInterface::class)
            && !$reflection->implementsInterface(PresenceChannelAuthorizerInterface::class)

        ) {
            throw ChannelAuthorizationException::notAnAuthorizer($className);
        }

        /** @var class-string<ChannelAuthorizerInterface|PresenceChannelAuthorizerInterface> $className */
        return new ChannelDefinition(
            pattern: $attributes[0]->newInstance()->pattern,
            authorizerClass: $className,
        );
    }
}
