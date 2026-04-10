<?php

declare(strict_types=1);

use Marko\Layout\HandleResolver;

describe('HandleResolver', function (): void {
    it('generates handle from simple route and controller', function (): void {
        $resolver = new HandleResolver();

        expect($resolver->generate('/products', 'App\Http\ProductController', 'index'))
            ->toBe('products_product_index');
    });

    it('uses first path segment as route portion', function (): void {
        $resolver = new HandleResolver();

        expect($resolver->generate('/products/{id}', 'App\Http\ProductController', 'show'))
            ->toBe('products_product_show');
    });

    it('uses index as route portion for root path', function (): void {
        $resolver = new HandleResolver();

        expect($resolver->generate('/', 'App\Http\HomeController', 'index'))
            ->toBe('index_home_index');
    });

    it('strips Controller suffix from class name and lowercases', function (): void {
        $resolver = new HandleResolver();

        expect($resolver->generate('/orders', 'App\Http\OrderController', 'index'))
            ->toBe('orders_order_index');
    });

    it('uses method name as action portion', function (): void {
        $resolver = new HandleResolver();

        expect($resolver->generate('/products', 'App\Http\ProductController', 'show'))
            ->toBe('products_product_show');
    });

    it('determines that default handle matches any handle', function (): void {
        $resolver = new HandleResolver();

        expect($resolver->matches('default', 'products_product_show'))->toBeTrue()
            ->and($resolver->matches('default', 'customer_order_index'))->toBeTrue();
    });

    it('determines that a wildcard prefix matches handles starting with that prefix', function (): void {
        $resolver = new HandleResolver();

        expect($resolver->matches('customer_*', 'customer_order_show'))->toBeTrue()
            ->and($resolver->matches('customer_*', 'customer_order_index'))->toBeTrue();
    });

    it('determines that a full handle only matches itself exactly', function (): void {
        $resolver = new HandleResolver();

        expect($resolver->matches('products_product_show', 'products_product_show'))->toBeTrue()
            ->and($resolver->matches('products_product_show', 'products_product_index'))->toBeFalse();
    });

    it('determines that a non-matching wildcard prefix does not match', function (): void {
        $resolver = new HandleResolver();

        expect($resolver->matches('customer_*', 'products_product_show'))->toBeFalse();
    });

    it('determines that a string without wildcard requires exact match', function (): void {
        $resolver = new HandleResolver();

        expect($resolver->matches('customer', 'customer_order_show'))->toBeFalse()
            ->and($resolver->matches('customer', 'customer'))->toBeTrue();
    });

    it('handles multi-segment routes using only first segment', function (): void {
        $resolver = new HandleResolver();

        expect($resolver->generate('/customer/orders/{id}', 'App\Http\OrderController', 'show'))
            ->toBe('customer_order_show');
    });

    it('handles controller names without Controller suffix gracefully', function (): void {
        $resolver = new HandleResolver();

        expect($resolver->generate('/products', 'App\Http\ProductHandler', 'index'))
            ->toBe('products_producthandler_index');
    });
});
