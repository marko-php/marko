<?php

declare(strict_types=1);

namespace Marko\Admin\Contracts;

interface MenuItemInterface
{
    public function getId(): string;

    public function getLabel(): string;

    public function getUrl(): string;

    public function getIcon(): string;

    public function getSortOrder(): int;

    public function getPermission(): string;
}
