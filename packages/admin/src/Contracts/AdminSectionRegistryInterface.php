<?php

declare(strict_types=1);

namespace Marko\Admin\Contracts;

use Marko\Admin\Exceptions\AdminException;

interface AdminSectionRegistryInterface
{
    /**
     * @throws AdminException
     */
    public function register(AdminSectionInterface $section): void;

    /**
     * @return array<AdminSectionInterface>
     */
    public function all(): array;

    /**
     * @throws AdminException
     */
    public function get(string $id): AdminSectionInterface;
}
