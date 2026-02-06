<?php

declare(strict_types=1);

namespace Marko\Admin;

use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\Contracts\AdminSectionRegistryInterface;
use Marko\Admin\Exceptions\AdminException;

class AdminSectionRegistry implements AdminSectionRegistryInterface
{
    /** @var array<string, AdminSectionInterface> */
    private array $sections = [];

    public function register(AdminSectionInterface $section): void
    {
        $id = $section->getId();

        if (isset($this->sections[$id])) {
            throw AdminException::duplicateSection($id);
        }

        $this->sections[$id] = $section;
    }

    /**
     * @return array<AdminSectionInterface>
     */
    public function all(): array
    {
        $sections = array_values($this->sections);

        usort($sections, fn (AdminSectionInterface $a, AdminSectionInterface $b): int => $a->getSortOrder() <=> $b->getSortOrder());

        return $sections;
    }

    public function get(string $id): AdminSectionInterface
    {
        if (!isset($this->sections[$id])) {
            throw AdminException::sectionNotFound($id);
        }

        return $this->sections[$id];
    }
}
