<?php

declare(strict_types=1);

use Marko\Admin\AdminSectionRegistry;
use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\Contracts\AdminSectionRegistryInterface;
use Marko\Admin\Discovery\AdminSectionDefinition;
use Marko\Admin\Exceptions\AdminException;
use Marko\Core\Container\Container;

class LazyRegistryBuildCounter
{
    public static int $builds = 0;
}

abstract class LazyRegistrySection implements AdminSectionInterface
{
    public function getLabel(): string
    {
        return static::class;
    }

    public function getIcon(): string
    {
        return '';
    }

    public function getSortOrder(): int
    {
        return 0;
    }

    public function getMenuItems(): array
    {
        return [];
    }
}

class LazyRegistryOrdersSection extends LazyRegistrySection
{
    public function __construct()
    {
        LazyRegistryBuildCounter::$builds++;
    }

    public function getId(): string
    {
        return 'orders';
    }
}

class LazyRegistryCatalogSection extends LazyRegistrySection
{
    public function getId(): string
    {
        return 'catalog';
    }
}

class LazyRegistryThrowingSection extends LazyRegistrySection
{
    public function __construct()
    {
        throw new RuntimeException('Table "orders" does not exist');
    }

    public function getId(): string
    {
        return 'broken';
    }
}

class LazyRegistryFlakySection extends LazyRegistrySection
{
    public static bool $fail = true;

    public function __construct()
    {
        if (self::$fail) {
            throw new RuntimeException('Connection refused');
        }
    }

    public function getId(): string
    {
        return 'flaky';
    }
}

class LazyRegistryNotASection {}

function lazyRegistryDefinition(
    string $className,
    string $id,
    int $sortOrder = 0,
): AdminSectionDefinition {
    return new AdminSectionDefinition(
        className: $className,
        id: $id,
        label: ucfirst($id),
        icon: '',
        sortOrder: $sortOrder,
    );
}

beforeEach(function (): void {
    LazyRegistryBuildCounter::$builds = 0;
});

it('creates AdminSectionRegistry implementing AdminSectionRegistryInterface', function (): void {
    $registry = new AdminSectionRegistry(new Container());

    expect($registry)->toBeInstanceOf(AdminSectionRegistryInterface::class);
});

it('registers sections and retrieves them sorted by sortOrder', function (): void {
    $registry = new AdminSectionRegistry(new Container());

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
    $registry = new AdminSectionRegistry(new Container());

    $section1 = createMockSection('catalog', 'Catalog', 10);
    $section2 = createMockSection('catalog', 'Catalog Duplicate', 20);

    $registry->register($section1);
    $registry->register($section2);
})->throws(AdminException::class, "Admin section with id 'catalog' is declared by both");

it('throws AdminException when getting nonexistent section', function (): void {
    $registry = new AdminSectionRegistry(new Container());

    $registry->get('nonexistent');
})->throws(AdminException::class, "Admin section 'nonexistent' not found");

it('does not build a section when its definition is registered', function (): void {
    $registry = new AdminSectionRegistry(new Container());

    $registry->registerDefinition(lazyRegistryDefinition(LazyRegistryOrdersSection::class, 'orders'));

    expect(LazyRegistryBuildCounter::$builds)->toBe(0);
});

it('builds a registered definition through the container on first get', function (): void {
    $registry = new AdminSectionRegistry(new Container());
    $registry->registerDefinition(lazyRegistryDefinition(LazyRegistryOrdersSection::class, 'orders'));

    $section = $registry->get('orders');

    expect($section)->toBeInstanceOf(LazyRegistryOrdersSection::class)
        ->and(LazyRegistryBuildCounter::$builds)->toBe(1);
});

it('builds each section only once and reuses the instance', function (): void {
    $registry = new AdminSectionRegistry(new Container());
    $registry->registerDefinition(lazyRegistryDefinition(LazyRegistryOrdersSection::class, 'orders'));

    $first = $registry->get('orders');
    $all = $registry->all();
    $again = $registry->get('orders');

    expect($all[0])->toBe($first)
        ->and($again)->toBe($first)
        ->and(LazyRegistryBuildCounter::$builds)->toBe(1);
});

it('sorts all sections by getSortOrder rather than the attribute sortOrder', function (): void {
    $registry = new AdminSectionRegistry(new Container());
    // The definition puts catalog last, but its getSortOrder() returns 0.
    $registry->registerDefinition(lazyRegistryDefinition(LazyRegistryCatalogSection::class, 'catalog', 99));
    $registry->register(createMockSection('content', 'Content', -10));
    $registry->register(createMockSection('reports', 'Reports', 30));

    $ids = array_map(
        static fn (AdminSectionInterface $section): string => $section->getId(),
        $registry->all(),
    );

    expect($ids)->toBe(['content', 'catalog', 'reports']);
});

it('keeps registration order for sections with equal sort order', function (): void {
    $registry = new AdminSectionRegistry(new Container());
    $registry->registerDefinition(lazyRegistryDefinition(LazyRegistryOrdersSection::class, 'orders'));
    $registry->register(createMockSection('content', 'Content', 0));
    $registry->registerDefinition(lazyRegistryDefinition(LazyRegistryCatalogSection::class, 'catalog'));

    $ids = array_map(
        static fn (AdminSectionInterface $section): string => $section->getId(),
        $registry->all(),
    );

    expect($ids)->toBe(['orders', 'content', 'catalog']);
});

it('wraps a constructor failure in an AdminException naming the section id and class', function (): void {
    $registry = new AdminSectionRegistry(new Container());
    $registry->registerDefinition(lazyRegistryDefinition(LazyRegistryThrowingSection::class, 'broken'));

    try {
        $registry->get('broken');
        $this->fail('Expected an AdminException');
    } catch (AdminException $e) {
        expect($e->getMessage())->toBe(
            "Admin section 'broken' (" . LazyRegistryThrowingSection::class
            . ') could not be built: Table "orders" does not exist',
        )
            ->and($e->getPrevious())->toBeInstanceOf(RuntimeException::class);
    }
});

it('fails all() when a section constructor throws', function (): void {
    $registry = new AdminSectionRegistry(new Container());
    $registry->registerDefinition(lazyRegistryDefinition(LazyRegistryThrowingSection::class, 'broken'));

    $registry->all();
})->throws(AdminException::class, LazyRegistryThrowingSection::class);

it('does not cache a failed build and retries on the next call', function (): void {
    $registry = new AdminSectionRegistry(new Container());
    $registry->registerDefinition(lazyRegistryDefinition(LazyRegistryFlakySection::class, 'flaky'));
    LazyRegistryFlakySection::$fail = true;

    expect(fn () => $registry->get('flaky'))->toThrow(AdminException::class, 'Connection refused');

    LazyRegistryFlakySection::$fail = false;

    expect($registry->get('flaky'))->toBeInstanceOf(LazyRegistryFlakySection::class);
});

it('throws sectionIdMismatch on first resolution when getId differs from the definition id', function (): void {
    $registry = new AdminSectionRegistry(new Container());
    $registry->registerDefinition(lazyRegistryDefinition(LazyRegistryCatalogSection::class, 'products'));

    $registry->get('products');
})->throws(
    AdminException::class,
    "Admin section '" . LazyRegistryCatalogSection::class
    . "' declares id 'products' in #[AdminSection] but getId() returns 'catalog'",
);

it('rejects a definition whose class does not exist without building anything', function (): void {
    $registry = new AdminSectionRegistry(new Container());

    $registry->registerDefinition(lazyRegistryDefinition('App\\Admin\\RemovedSection', 'removed'));
})->throws(AdminException::class, "Admin section class 'App\\Admin\\RemovedSection' does not exist");

it('rejects a definition whose class does not implement AdminSectionInterface', function (): void {
    $registry = new AdminSectionRegistry(new Container());

    $registry->registerDefinition(lazyRegistryDefinition(LazyRegistryNotASection::class, 'not-a-section'));
})->throws(
    AdminException::class,
    "Class '" . LazyRegistryNotASection::class . "' has #[AdminSection] attribute but does not implement AdminSectionInterface",
);

it('rejects a definition whose id is already taken, naming both classes', function (): void {
    $registry = new AdminSectionRegistry(new Container());
    $registry->registerDefinition(lazyRegistryDefinition(LazyRegistryOrdersSection::class, 'orders'));

    $registry->registerDefinition(lazyRegistryDefinition(LazyRegistryCatalogSection::class, 'orders'));
})->throws(
    AdminException::class,
    "Admin section with id 'orders' is declared by both '" . LazyRegistryOrdersSection::class
    . "' and '" . LazyRegistryCatalogSection::class . "'",
);

it('rejects a manual section whose id is taken by a definition', function (): void {
    $registry = new AdminSectionRegistry(new Container());
    $registry->registerDefinition(lazyRegistryDefinition(LazyRegistryOrdersSection::class, 'orders'));

    $registry->register(createMockSection('orders', 'Orders', 0));
})->throws(
    AdminException::class,
    "Admin section with id 'orders' is declared by both '" . LazyRegistryOrdersSection::class . "' and '",
);

it('keeps registering built sections by hand', function (): void {
    $registry = new AdminSectionRegistry(new Container());
    $section = createMockSection('reports', 'Reports', 0);

    $registry->register($section);

    expect($registry->get('reports'))->toBe($section);
});

function createMockSection(
    string $id,
    string $label,
    int $sortOrder,
): AdminSectionInterface {
    return new readonly class ($id, $label, $sortOrder) implements AdminSectionInterface
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
