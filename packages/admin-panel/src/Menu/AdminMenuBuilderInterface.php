<?php

declare(strict_types=1);

namespace Marko\AdminPanel\Menu;

use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\Contracts\MenuItemInterface;
use Marko\AdminAuth\Entity\AdminUserInterface;

interface AdminMenuBuilderInterface
{
    /**
     * Build the sidebar navigation menu filtered by user permissions.
     *
     * Each element is an array with:
     *   - 'section' => AdminSectionInterface
     *   - 'items'   => array<MenuItemInterface> (filtered and sorted)
     *   - 'active'  => bool (whether this section contains the active item)
     *   - 'activeItemId' => string|null (ID of the active menu item, or null)
     *
     * @return array<int, array{section: AdminSectionInterface, items: array<MenuItemInterface>, active: bool, activeItemId: string|null}>
     */
    public function build(
        AdminUserInterface $user,
        string $currentPath = '',
    ): array;

    /**
     * Build the dashboard section list filtered by user permissions.
     *
     * Returns sections that have at least one menu item the user can access.
     *
     * @return array<AdminSectionInterface>
     */
    public function buildDashboardSections(
        AdminUserInterface $user,
    ): array;
}
