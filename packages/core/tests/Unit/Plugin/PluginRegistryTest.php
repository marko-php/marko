<?php

declare(strict_types=1);

use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;
use Marko\Core\Exceptions\PluginException;
use Marko\Core\Plugin\PluginDefinition;
use Marko\Core\Plugin\PluginRegistry;

// Test fixtures
class OrderService
{
    /** @noinspection PhpUnused - Test fixture */
    public function placeOrder(): string
    {
        return 'order placed';
    }
}

#[Plugin(target: OrderService::class)]
class OrderValidationPlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    #[Before(sortOrder: 10)]
    public function placeOrder(): void {}
}

#[Plugin(target: OrderService::class)]
class OrderLoggingPlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    #[After(sortOrder: 20)]
    public function placeOrder(): void {}
}

class PaymentService
{
    /** @noinspection PhpUnused - Test fixture */
    public function processPayment(): string
    {
        return 'payment processed';
    }
}

#[Plugin(target: PaymentService::class)]
class PaymentAuditPlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    #[After(sortOrder: 5)]
    public function processPayment(): void {}
}

it('collects all plugins for a given target class with new definition format', function (): void {
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: OrderValidationPlugin::class,
        targetClass: OrderService::class,
        beforeMethods: ['placeOrder' => ['pluginMethod' => 'placeOrder', 'sortOrder' => 10]],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: OrderLoggingPlugin::class,
        targetClass: OrderService::class,
        afterMethods: ['placeOrder' => ['pluginMethod' => 'placeOrder', 'sortOrder' => 20]],
    ));

    $orderPlugins = $registry->getPluginsFor(OrderService::class);

    expect($orderPlugins)
        ->toBeArray()
        ->toHaveCount(2);

    $pluginClasses = array_map(fn (PluginDefinition $p) => $p->pluginClass, $orderPlugins);
    expect($pluginClasses)
        ->toContain(OrderValidationPlugin::class)
        ->toContain(OrderLoggingPlugin::class);
});

it('collects all plugins for a given target class', function (): void {
    $registry = new PluginRegistry();

    // Register plugins for OrderService
    $registry->register(new PluginDefinition(
        pluginClass: OrderValidationPlugin::class,
        targetClass: OrderService::class,
        beforeMethods: ['placeOrder' => ['pluginMethod' => 'placeOrder', 'sortOrder' => 10]],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: OrderLoggingPlugin::class,
        targetClass: OrderService::class,
        afterMethods: ['placeOrder' => ['pluginMethod' => 'placeOrder', 'sortOrder' => 20]],
    ));

    // Register plugin for PaymentService
    $registry->register(new PluginDefinition(
        pluginClass: PaymentAuditPlugin::class,
        targetClass: PaymentService::class,
        afterMethods: ['processPayment' => ['pluginMethod' => 'processPayment', 'sortOrder' => 5]],
    ));

    // Get plugins for OrderService
    $orderPlugins = $registry->getPluginsFor(OrderService::class);

    expect($orderPlugins)
        ->toBeArray()
        ->toHaveCount(2);

    $pluginClasses = array_map(fn (PluginDefinition $p) => $p->pluginClass, $orderPlugins);
    expect($pluginClasses)
        ->toContain(OrderValidationPlugin::class)
        ->toContain(OrderLoggingPlugin::class)
        ->not->toContain(PaymentAuditPlugin::class);
});

// Additional fixtures for sorting test
class SortableService
{
    public function doAction(): void {}
}

#[Plugin(target: SortableService::class)]
class HighPriorityPlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    #[Before(sortOrder: 5)]
    public function doAction(): void {}
}

#[Plugin(target: SortableService::class)]
class MediumPriorityPlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    #[Before(sortOrder: 15)]
    public function doAction(): void {}
}

#[Plugin(target: SortableService::class)]
class LowPriorityPlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    #[Before(sortOrder: 30)]
    public function doAction(): void {}
}

it('sorts plugins by sortOrder with new definition format', function (): void {
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: MediumPriorityPlugin::class,
        targetClass: SortableService::class,
        beforeMethods: ['doAction' => ['pluginMethod' => 'doAction', 'sortOrder' => 15]],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: HighPriorityPlugin::class,
        targetClass: SortableService::class,
        beforeMethods: ['doAction' => ['pluginMethod' => 'doAction', 'sortOrder' => 5]],
    ));

    $beforeMethods = $registry->getBeforeMethodsFor(SortableService::class, 'doAction');

    expect($beforeMethods)
        ->toBeArray()
        ->toHaveCount(2);

    $sortOrders = array_map(fn (array $method) => $method['sortOrder'], $beforeMethods);
    expect($sortOrders)->toBe([5, 15]);
});

it('sorts plugins by sortOrder (lower runs first)', function (): void {
    $registry = new PluginRegistry();

    // Register in non-sorted order
    $registry->register(new PluginDefinition(
        pluginClass: MediumPriorityPlugin::class,
        targetClass: SortableService::class,
        beforeMethods: ['doAction' => ['pluginMethod' => 'doAction', 'sortOrder' => 15]],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: LowPriorityPlugin::class,
        targetClass: SortableService::class,
        beforeMethods: ['doAction' => ['pluginMethod' => 'doAction', 'sortOrder' => 30]],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: HighPriorityPlugin::class,
        targetClass: SortableService::class,
        beforeMethods: ['doAction' => ['pluginMethod' => 'doAction', 'sortOrder' => 5]],
    ));

    // Get sorted before methods for doAction
    $beforeMethods = $registry->getBeforeMethodsFor(SortableService::class, 'doAction');

    expect($beforeMethods)
        ->toBeArray()
        ->toHaveCount(3);

    // Verify sort order - lower sortOrder should come first
    $sortOrders = array_map(fn (array $method) => $method['sortOrder'], $beforeMethods);
    expect($sortOrders)->toBe([5, 15, 30]);

    // Verify the plugins are in correct order
    $pluginClasses = array_map(fn (array $method) => $method['pluginClass'], $beforeMethods);
    expect($pluginClasses)->toBe([
        HighPriorityPlugin::class,
        MediumPriorityPlugin::class,
        LowPriorityPlugin::class,
    ]);
});

// Fixtures for new target-method-keyed registry tests
class InvoiceService
{
    public function create(): void {}
    public function cancel(): void {}
}

#[Plugin(target: InvoiceService::class)]
class InvoiceValidationPlugin
{
    /** @noinspection PhpUnused */
    #[Before(sortOrder: 1)]
    public function create(): void {}
}

#[Plugin(target: InvoiceService::class)]
class InvoiceAuditPlugin
{
    /** @noinspection PhpUnused */
    #[After(sortOrder: 2)]
    public function cancel(): void {}
}

#[Plugin(target: InvoiceService::class)]
class InvoiceNotifyPlugin
{
    /** @noinspection PhpUnused */
    #[After(method: 'create', sortOrder: 3)]
    public function notifyCreate(): void {}
}

it('matches before plugins by target method name directly', function (): void {
    $registry = new PluginRegistry();
    $registry->register(new PluginDefinition(
        pluginClass: InvoiceValidationPlugin::class,
        targetClass: InvoiceService::class,
        beforeMethods: ['create' => ['pluginMethod' => 'create', 'sortOrder' => 1]],
    ));

    $result = $registry->getBeforeMethodsFor(InvoiceService::class, 'create');

    expect($result)->toHaveCount(1)
        ->and($result[0]['pluginClass'])->toBe(InvoiceValidationPlugin::class)
        ->and($result[0]['method'])->toBe('create');
});

it('matches after plugins by target method name directly', function (): void {
    $registry = new PluginRegistry();
    $registry->register(new PluginDefinition(
        pluginClass: InvoiceAuditPlugin::class,
        targetClass: InvoiceService::class,
        afterMethods: ['cancel' => ['pluginMethod' => 'cancel', 'sortOrder' => 2]],
    ));

    $result = $registry->getAfterMethodsFor(InvoiceService::class, 'cancel');

    expect($result)->toHaveCount(1)
        ->and($result[0]['pluginClass'])->toBe(InvoiceAuditPlugin::class)
        ->and($result[0]['method'])->toBe('cancel');
});

it('returns plugin method name in result for invocation', function (): void {
    $registry = new PluginRegistry();
    $registry->register(new PluginDefinition(
        pluginClass: InvoiceValidationPlugin::class,
        targetClass: InvoiceService::class,
        beforeMethods: ['create' => ['pluginMethod' => 'create', 'sortOrder' => 1]],
    ));

    $result = $registry->getBeforeMethodsFor(InvoiceService::class, 'create');

    expect($result[0])->toHaveKey('method')
        ->and($result[0]['method'])->toBe('create');
});

it('returns correct plugin method name when method param differs from target', function (): void {
    $registry = new PluginRegistry();
    $registry->register(new PluginDefinition(
        pluginClass: InvoiceNotifyPlugin::class,
        targetClass: InvoiceService::class,
        afterMethods: ['create' => ['pluginMethod' => 'notifyCreate', 'sortOrder' => 3]],
    ));

    $result = $registry->getAfterMethodsFor(InvoiceService::class, 'create');

    expect($result)->toHaveCount(1)
        ->and($result[0]['method'])->toBe('notifyCreate')
        ->and($result[0]['pluginClass'])->toBe(InvoiceNotifyPlugin::class);
});

it('sorts matched methods by sortOrder ascending', function (): void {
    $registry = new PluginRegistry();
    $registry->register(new PluginDefinition(
        pluginClass: LowPriorityPlugin::class,
        targetClass: SortableService::class,
        beforeMethods: ['doAction' => ['pluginMethod' => 'doAction', 'sortOrder' => 30]],
    ));
    $registry->register(new PluginDefinition(
        pluginClass: HighPriorityPlugin::class,
        targetClass: SortableService::class,
        beforeMethods: ['doAction' => ['pluginMethod' => 'doAction', 'sortOrder' => 5]],
    ));

    $result = $registry->getBeforeMethodsFor(SortableService::class, 'doAction');

    expect($result[0]['sortOrder'])->toBe(5)
        ->and($result[1]['sortOrder'])->toBe(30);
});

it('returns empty array when no plugins match target method', function (): void {
    $registry = new PluginRegistry();
    $registry->register(new PluginDefinition(
        pluginClass: InvoiceValidationPlugin::class,
        targetClass: InvoiceService::class,
        beforeMethods: ['create' => ['pluginMethod' => 'create', 'sortOrder' => 1]],
    ));

    $result = $registry->getBeforeMethodsFor(InvoiceService::class, 'cancel');

    expect($result)->toBeArray()->toBeEmpty();
});

// Fixtures for cross-class duplicate sort order tests

class ConflictTargetService
{
    public function save(): void {}
}

#[Plugin(target: ConflictTargetService::class)]
class ConflictPluginA
{
    /** @noinspection PhpUnused - Invoked via reflection */
    #[Before(sortOrder: 10)]
    public function save(): void {}
}

#[Plugin(target: ConflictTargetService::class)]
class ConflictPluginB
{
    /** @noinspection PhpUnused - Invoked via reflection */
    #[Before(sortOrder: 10)]
    public function save(): void {}
}

#[Plugin(target: ConflictTargetService::class)]
class ConflictAfterPluginA
{
    /** @noinspection PhpUnused - Invoked via reflection */
    #[After(sortOrder: 10)]
    public function save(): void {}
}

#[Plugin(target: ConflictTargetService::class)]
class ConflictAfterPluginB
{
    /** @noinspection PhpUnused - Invoked via reflection */
    #[After(sortOrder: 10)]
    public function save(): void {}
}

#[Plugin(target: ConflictTargetService::class)]
class DifferentSortOrderPlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    #[Before(sortOrder: 20)]
    public function save(): void {}
}

it('throws PluginException when two plugins have same timing, target method, and sort order', function (): void {
    $registry = new PluginRegistry();
    $registry->register(new PluginDefinition(
        pluginClass: ConflictPluginA::class,
        targetClass: ConflictTargetService::class,
        beforeMethods: ['save' => ['pluginMethod' => 'save', 'sortOrder' => 10]],
    ));

    expect(fn () => $registry->register(new PluginDefinition(
        pluginClass: ConflictPluginB::class,
        targetClass: ConflictTargetService::class,
        beforeMethods: ['save' => ['pluginMethod' => 'save', 'sortOrder' => 10]],
    )))->toThrow(PluginException::class);
});

it('allows two plugins with same timing and target method but different sort orders', function (): void {
    $registry = new PluginRegistry();
    $registry->register(new PluginDefinition(
        pluginClass: ConflictPluginA::class,
        targetClass: ConflictTargetService::class,
        beforeMethods: ['save' => ['pluginMethod' => 'save', 'sortOrder' => 10]],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: DifferentSortOrderPlugin::class,
        targetClass: ConflictTargetService::class,
        beforeMethods: ['save' => ['pluginMethod' => 'save', 'sortOrder' => 20]],
    ));

    expect($registry->getBeforeMethodsFor(ConflictTargetService::class, 'save'))->toHaveCount(2);
});

it('provides helpful error message with both plugin classes for cross-class conflict', function (): void {
    $registry = new PluginRegistry();
    $registry->register(new PluginDefinition(
        pluginClass: ConflictPluginA::class,
        targetClass: ConflictTargetService::class,
        beforeMethods: ['save' => ['pluginMethod' => 'save', 'sortOrder' => 10]],
    ));

    try {
        $registry->register(new PluginDefinition(
            pluginClass: ConflictPluginB::class,
            targetClass: ConflictTargetService::class,
            beforeMethods: ['save' => ['pluginMethod' => 'save', 'sortOrder' => 10]],
        ));
        expect(false)->toBeTrue('Expected PluginException to be thrown');
    } catch (PluginException $e) {
        expect($e->getMessage())->toContain('ConflictPluginA')
            ->and($e->getMessage())->toContain('ConflictPluginB')
            ->and($e->getMessage())->toContain('before')
            ->and($e->getMessage())->toContain('save')
            ->and($e->getContext())->toContain('ConflictPluginB')
            ->and($e->getSuggestion())->not->toBeEmpty();
    }
});
