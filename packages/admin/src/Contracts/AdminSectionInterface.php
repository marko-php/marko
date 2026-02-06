<?php

declare(strict_types=1);

namespace Marko\Admin\Contracts;

interface AdminSectionInterface
{
    public function getId(): string;

    public function getLabel(): string;

    public function getIcon(): string;

    public function getSortOrder(): int;

    /**
     * @return array<MenuItemInterface>
     */
    public function getMenuItems(): array;
}
