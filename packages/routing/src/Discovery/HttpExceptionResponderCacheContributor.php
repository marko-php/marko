<?php

declare(strict_types=1);

namespace Marko\Routing\Discovery;

use Marko\Core\Discovery\DiscoveryCacheContributorInterface;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Module\ModuleManifest;
use Marko\Routing\Exceptions\HttpExceptionResponderException;
use Marko\Routing\Http\HttpExceptionResponderInterface;

/**
 * Stores the discovered HTTP exception responders in the discovery cache, in
 * priority order, so a cached boot builds the responder list without scanning
 * the modules.
 */
readonly class HttpExceptionResponderCacheContributor implements DiscoveryCacheContributorInterface
{
    public const string KEY = 'http_exception_responders';

    public function __construct(
        private HttpExceptionResponderDiscovery $discovery,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    /**
     * @param array<ModuleManifest> $modules
     * @return array<int, array{className: string, priority: int}>
     * @throws HttpExceptionResponderException
     */
    public function compile(
        array $modules,
    ): array {
        return array_map(
            fn (HttpExceptionResponderDefinition $definition): array => [
                'className' => $definition->className,
                'priority' => $definition->priority,
            ],
            $this->discovery->discoverAll($modules),
        );
    }

    /**
     * Rebuild the responder definitions from a cached section, keeping its order.
     *
     * @param array<mixed> $section
     * @return array<int, HttpExceptionResponderDefinition>
     * @throws DiscoveryCacheException
     */
    public function hydrate(
        array $section,
    ): array {
        $definitions = [];

        foreach ($section as $index => $record) {
            if (!is_array($record)) {
                throw DiscoveryCacheException::malformedSection(self::KEY, "responder $index must be an array");
            }

            $className = $record['className'] ?? null;
            $priority = $record['priority'] ?? null;

            if (!is_string($className)) {
                throw DiscoveryCacheException::malformedSection(
                    self::KEY,
                    "responder $index.className must be a string",
                );
            }

            if (!is_int($priority)) {
                throw DiscoveryCacheException::malformedSection(
                    self::KEY,
                    "responder $index.priority must be an int",
                );
            }

            /** @var class-string<HttpExceptionResponderInterface> $className */
            $definitions[] = new HttpExceptionResponderDefinition(
                className: $className,
                priority: $priority,
            );
        }

        return $definitions;
    }
}
