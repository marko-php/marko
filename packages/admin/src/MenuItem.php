<?php

declare(strict_types=1);

namespace Marko\Admin;

use Marko\Admin\Contracts\MenuItemInterface;

readonly class MenuItem implements MenuItemInterface
{
    public function __construct(
        private string $id,
        private string $label,
        private string $url,
        private string $icon = '',
        private int $sortOrder = 0,
        private string $permission = '',
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function getPermission(): string
    {
        return $this->permission;
    }
}
