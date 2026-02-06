<?php

declare(strict_types=1);

namespace Marko\Admin\Contracts;

interface AdminSectionRegistryInterface
{
    public function register(AdminSectionInterface $section): void;

    /**
     * @return array<AdminSectionInterface>
     */
    public function all(): array;

    public function get(string $id): AdminSectionInterface;
}
