<?php

declare(strict_types=1);

use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Exceptions\BindingConflictException;
use Marko\Core\Module\ModuleManifest;

// Test fixtures
interface PaymentInterface {}
class StripePayment implements PaymentInterface {}
class PayPalPayment implements PaymentInterface {}
class SquarePayment implements PaymentInterface {}

it('registers bindings from module manifest to container', function (): void {
    $container = new Container();
    $registry = new BindingRegistry($container);

    $module = new ModuleManifest(
        name: 'vendor/payment',
        version: '1.0.0',
        bindings: [
            PaymentInterface::class => StripePayment::class,
        ],
        source: 'vendor',
    );

    $registry->registerModule($module);

    expect($container->get(PaymentInterface::class))
        ->toBeInstanceOf(StripePayment::class);
});

it('allows app module to override vendor module binding', function (): void {
    $container = new Container();
    $registry = new BindingRegistry($container);

    $vendorModule = new ModuleManifest(
        name: 'vendor/payment',
        version: '1.0.0',
        bindings: [
            PaymentInterface::class => StripePayment::class,
        ],
        source: 'vendor',
    );

    $appModule = new ModuleManifest(
        name: 'app/payment',
        version: '1.0.0',
        bindings: [
            PaymentInterface::class => PayPalPayment::class,
        ],
        source: 'app',
    );

    $registry->registerModule($vendorModule);
    $registry->registerModule($appModule);

    expect($container->get(PaymentInterface::class))
        ->toBeInstanceOf(PayPalPayment::class);
});

it('allows modules directory to override vendor binding', function (): void {
    $container = new Container();
    $registry = new BindingRegistry($container);

    $vendorModule = new ModuleManifest(
        name: 'vendor/payment',
        version: '1.0.0',
        bindings: [
            PaymentInterface::class => StripePayment::class,
        ],
        source: 'vendor',
    );

    $modulesModule = new ModuleManifest(
        name: 'custom/payment',
        version: '1.0.0',
        bindings: [
            PaymentInterface::class => SquarePayment::class,
        ],
        source: 'modules',
    );

    $registry->registerModule($vendorModule);
    $registry->registerModule($modulesModule);

    expect($container->get(PaymentInterface::class))
        ->toBeInstanceOf(SquarePayment::class);
});

it('allows app to override modules directory binding', function (): void {
    $container = new Container();
    $registry = new BindingRegistry($container);

    $modulesModule = new ModuleManifest(
        name: 'custom/payment',
        version: '1.0.0',
        bindings: [
            PaymentInterface::class => SquarePayment::class,
        ],
        source: 'modules',
    );

    $appModule = new ModuleManifest(
        name: 'app/payment',
        version: '1.0.0',
        bindings: [
            PaymentInterface::class => PayPalPayment::class,
        ],
        source: 'app',
    );

    $registry->registerModule($modulesModule);
    $registry->registerModule($appModule);

    expect($container->get(PaymentInterface::class))
        ->toBeInstanceOf(PayPalPayment::class);
});

it('throws BindingConflictException when same-priority modules bind same interface', function (): void {
    $container = new Container();
    $registry = new BindingRegistry($container);

    $vendorModule1 = new ModuleManifest(
        name: 'vendor/stripe',
        version: '1.0.0',
        bindings: [
            PaymentInterface::class => StripePayment::class,
        ],
        source: 'vendor',
    );

    $vendorModule2 = new ModuleManifest(
        name: 'vendor/paypal',
        version: '1.0.0',
        bindings: [
            PaymentInterface::class => PayPalPayment::class,
        ],
        source: 'vendor',
    );

    $registry->registerModule($vendorModule1);

    expect(fn () => $registry->registerModule($vendorModule2))
        ->toThrow(BindingConflictException::class);
});

it('includes both module names in BindingConflictException message', function (): void {
    $container = new Container();
    $registry = new BindingRegistry($container);

    $vendorModule1 = new ModuleManifest(
        name: 'vendor/stripe',
        version: '1.0.0',
        bindings: [
            PaymentInterface::class => StripePayment::class,
        ],
        source: 'vendor',
    );

    $vendorModule2 = new ModuleManifest(
        name: 'vendor/paypal',
        version: '1.0.0',
        bindings: [
            PaymentInterface::class => PayPalPayment::class,
        ],
        source: 'vendor',
    );

    $registry->registerModule($vendorModule1);

    $exception = null;

    try {
        $registry->registerModule($vendorModule2);
    } catch (BindingConflictException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull()
        ->and($exception->getMessage())->toContain('vendor/stripe')
        ->and($exception->getMessage())->toContain('vendor/paypal');
});

it('includes resolution suggestions in BindingConflictException', function (): void {
    $container = new Container();
    $registry = new BindingRegistry($container);

    $vendorModule1 = new ModuleManifest(
        name: 'vendor/stripe',
        version: '1.0.0',
        bindings: [
            PaymentInterface::class => StripePayment::class,
        ],
        source: 'vendor',
    );

    $vendorModule2 = new ModuleManifest(
        name: 'vendor/paypal',
        version: '1.0.0',
        bindings: [
            PaymentInterface::class => PayPalPayment::class,
        ],
        source: 'vendor',
    );

    $registry->registerModule($vendorModule1);

    $exception = null;

    try {
        $registry->registerModule($vendorModule2);
    } catch (BindingConflictException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull()
        ->and($exception->getSuggestion())->toContain('Preference');
});

it('resolves interface to bound implementation via container', function (): void {
    $container = new Container();
    $registry = new BindingRegistry($container);

    $module = new ModuleManifest(
        name: 'vendor/payment',
        version: '1.0.0',
        bindings: [
            PaymentInterface::class => StripePayment::class,
        ],
        source: 'vendor',
    );

    $registry->registerModule($module);

    // Container should resolve the interface to the bound implementation
    $instance = $container->get(PaymentInterface::class);

    expect($instance)->toBeInstanceOf(PaymentInterface::class)
        ->and($instance)->toBeInstanceOf(StripePayment::class);
});

it('processes bindings in module load order', function (): void {
    $container = new Container();
    $registry = new BindingRegistry($container);

    // Simulate the order modules are loaded by the dependency resolver
    // Modules at different priority levels are loaded in the order they're passed
    $vendorModule = new ModuleManifest(
        name: 'vendor/payment',
        version: '1.0.0',
        bindings: [
            PaymentInterface::class => StripePayment::class,
        ],
        source: 'vendor',
    );

    $modulesModule = new ModuleManifest(
        name: 'custom/payment',
        version: '1.0.0',
        bindings: [
            PaymentInterface::class => SquarePayment::class,
        ],
        source: 'modules',
    );

    $appModule = new ModuleManifest(
        name: 'app/payment',
        version: '1.0.0',
        bindings: [
            PaymentInterface::class => PayPalPayment::class,
        ],
        source: 'app',
    );

    // Process in load order (typically vendor -> modules -> app)
    $registry->registerModule($vendorModule);
    $registry->registerModule($modulesModule);
    $registry->registerModule($appModule);

    // Final binding should be from app (highest priority)
    expect($container->get(PaymentInterface::class))
        ->toBeInstanceOf(PayPalPayment::class);
});
