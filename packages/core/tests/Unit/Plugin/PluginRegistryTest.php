<?php

declare(strict_types=1);

use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;
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
    public function beforePlaceOrder(): void {}
}

#[Plugin(target: OrderService::class)]
class OrderLoggingPlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    #[After(sortOrder: 20)]
    public function afterPlaceOrder(): void {}
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
    public function afterProcessPayment(): void {}
}

it('collects all plugins for a given target class', function (): void {
    $registry = new PluginRegistry();

    // Register plugins for OrderService
    $registry->register(new PluginDefinition(
        pluginClass: OrderValidationPlugin::class,
        targetClass: OrderService::class,
        beforeMethods: ['beforePlaceOrder' => 10],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: OrderLoggingPlugin::class,
        targetClass: OrderService::class,
        afterMethods: ['afterPlaceOrder' => 20],
    ));

    // Register plugin for PaymentService
    $registry->register(new PluginDefinition(
        pluginClass: PaymentAuditPlugin::class,
        targetClass: PaymentService::class,
        afterMethods: ['afterProcessPayment' => 5],
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
    #[Before(sortOrder: 5)]
    public function beforeDoAction(): void {}
}

#[Plugin(target: SortableService::class)]
class MediumPriorityPlugin
{
    #[Before(sortOrder: 15)]
    public function beforeDoAction(): void {}
}

#[Plugin(target: SortableService::class)]
class LowPriorityPlugin
{
    #[Before(sortOrder: 30)]
    public function beforeDoAction(): void {}
}

it('sorts plugins by sortOrder (lower runs first)', function (): void {
    $registry = new PluginRegistry();

    // Register in non-sorted order
    $registry->register(new PluginDefinition(
        pluginClass: MediumPriorityPlugin::class,
        targetClass: SortableService::class,
        beforeMethods: ['beforeDoAction' => 15],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: LowPriorityPlugin::class,
        targetClass: SortableService::class,
        beforeMethods: ['beforeDoAction' => 30],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: HighPriorityPlugin::class,
        targetClass: SortableService::class,
        beforeMethods: ['beforeDoAction' => 5],
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
