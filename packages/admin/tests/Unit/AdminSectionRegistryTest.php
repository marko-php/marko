<?php

declare(strict_types=1);

use Marko\Admin\AdminSectionRegistry;
use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\Contracts\AdminSectionRegistryInterface;
use Marko\Admin\Exceptions\AdminException;

it('creates AdminSectionRegistry implementing AdminSectionRegistryInterface', function (): void {
    $registry = new AdminSectionRegistry();

    expect($registry)->toBeInstanceOf(AdminSectionRegistryInterface::class);
});

it('registers sections and retrieves them sorted by sortOrder', function (): void {
    $registry = new AdminSectionRegistry();

    $sectionC = createMockSection('content', 'Content', 30);
    $sectionA = createMockSection('catalog', 'Catalog', 10);
    $sectionB = createMockSection('sales', 'Sales', 20);

    $registry->register($sectionC);
    $registry->register($sectionA);
    $registry->register($sectionB);

    $all = $registry->all();

    expect($all)->toHaveCount(3)
        ->and($all[0]->getId())->toBe('catalog')
        ->and($all[1]->getId())->toBe('sales')
        ->and($all[2]->getId())->toBe('content');

    // Verify get by id
    $section = $registry->get('sales');
    expect($section->getId())->toBe('sales')
        ->and($section->getLabel())->toBe('Sales');
});

it('throws AdminException when registering duplicate section id', function (): void {
    $registry = new AdminSectionRegistry();

    $section1 = createMockSection('catalog', 'Catalog', 10);
    $section2 = createMockSection('catalog', 'Catalog Duplicate', 20);

    $registry->register($section1);
    $registry->register($section2);
})->throws(AdminException::class, "Admin section with id 'catalog' is already registered");

it('throws AdminException when getting nonexistent section', function (): void {
    $registry = new AdminSectionRegistry();

    $registry->get('nonexistent');
})->throws(AdminException::class, "Admin section 'nonexistent' not found");

function createMockSection(string $id, string $label, int $sortOrder): AdminSectionInterface
{
    return new class ($id, $label, $sortOrder) implements AdminSectionInterface
    {
        public function __construct(
            private string $id,
            private string $label,
            private int $sortOrder,
        ) {}

        public function getId(): string
        {
            return $this->id;
        }

        public function getLabel(): string
        {
            return $this->label;
        }

        public function getIcon(): string
        {
            return '';
        }

        public function getSortOrder(): int
        {
            return $this->sortOrder;
        }

        public function getMenuItems(): array
        {
            return [];
        }
    };
}
