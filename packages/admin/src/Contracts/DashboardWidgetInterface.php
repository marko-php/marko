<?php

declare(strict_types=1);

namespace Marko\Admin\Contracts;

interface DashboardWidgetInterface
{
    public function getId(): string;

    public function getLabel(): string;

    public function getSortOrder(): int;

    public function render(): string;
}
