<?php

declare(strict_types=1);

namespace Marko\AdminPanel\Config;

use Marko\Config\ConfigRepositoryInterface;

readonly class AdminPanelConfig implements AdminPanelConfigInterface
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function getPageTitle(): string
    {
        return $this->config->getString('admin-panel.page_title');
    }

    public function getItemsPerPage(): int
    {
        return $this->config->getInt('admin-panel.items_per_page');
    }
}
