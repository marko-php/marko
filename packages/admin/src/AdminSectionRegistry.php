<?php

declare(strict_types=1);

namespace Marko\Admin;

use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\Contracts\AdminSectionRegistryInterface;
use Marko\Admin\Discovery\AdminSectionDefinition;
use Marko\Admin\Exceptions\AdminException;
use Marko\Core\Container\ContainerInterface;
use Throwable;

/**
 * Holds the admin sections, keyed by id.
 *
 * A section registered by definition is built through the container the first
 * time get() or all() needs it, then kept for the rest of the process. Boot,
 * CLI commands and requests that never ask for a section build nothing.
 */
class AdminSectionRegistry implements AdminSectionRegistryInterface
{
    /**
     * Every section in registration order: a definition until it is built, the
     * built (or hand-registered) section after.
     *
     * @var array<string, AdminSectionDefinition|AdminSectionInterface>
     */
    private array $sections = [];

    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    /**
     * @throws AdminException
     */
    public function register(
        AdminSectionInterface $section,
    ): void {
        $id = $section->getId();

        $this->assertIdAvailable($id, $section::class);

        $this->sections[$id] = $section;
    }

    /**
     * Checks the class without instantiating it, so a section whose constructor
     * needs the database or other runtime services never runs at registration.
     *
     * @throws AdminException
     */
    public function registerDefinition(
        AdminSectionDefinition $definition,
    ): void {
        if (!class_exists($definition->className)) {
            throw AdminException::sectionClassNotFound($definition->className);
        }

        if (!is_subclass_of($definition->className, AdminSectionInterface::class)) {
            throw AdminException::sectionMustImplementInterface($definition->className);
        }

        $this->assertIdAvailable($definition->id, $definition->className);

        $this->sections[$definition->id] = $definition;
    }

    /**
     * Builds every section not built yet, then sorts by getSortOrder(). Sections
     * with the same sort order keep their registration order.
     *
     * @return array<AdminSectionInterface>
     *
     * @throws AdminException
     */
    public function all(): array
    {
        $sections = array_map(
            fn (string $id): AdminSectionInterface => $this->get($id),
            array_keys($this->sections),
        );

        usort(
            $sections,
            fn (AdminSectionInterface $a, AdminSectionInterface $b): int => $a->getSortOrder() <=> $b->getSortOrder(),
        );

        return $sections;
    }

    /**
     * @throws AdminException
     */
    public function get(
        string $id,
    ): AdminSectionInterface {
        if (!isset($this->sections[$id])) {
            throw AdminException::sectionNotFound($id);
        }

        $section = $this->sections[$id];

        if ($section instanceof AdminSectionDefinition) {
            $section = $this->build($section);
            $this->sections[$id] = $section;
        }

        return $section;
    }

    /**
     * @throws AdminException
     */
    private function build(
        AdminSectionDefinition $definition,
    ): AdminSectionInterface {
        try {
            $section = $this->container->get($definition->className);
        } catch (Throwable $e) {
            throw AdminException::sectionBuildFailed($definition->id, $definition->className, $e);
        }

        // A Preference can swap the class for one that no longer implements the interface.
        if (!$section instanceof AdminSectionInterface) {
            throw AdminException::sectionMustImplementInterface($definition->className);
        }

        // The registry keys on the attribute id; permissions are registered under it too.
        if ($section->getId() !== $definition->id) {
            throw AdminException::sectionIdMismatch($definition->className, $definition->id, $section->getId());
        }

        return $section;
    }

    /**
     * @throws AdminException
     */
    private function assertIdAvailable(
        string $id,
        string $className,
    ): void {
        if (!isset($this->sections[$id])) {
            return;
        }

        $existing = $this->sections[$id];
        $existingClass = $existing instanceof AdminSectionDefinition ? $existing->className : $existing::class;

        throw AdminException::duplicateSection($id, $existingClass, $className);
    }
}
