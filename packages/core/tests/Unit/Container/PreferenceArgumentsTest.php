<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Exceptions\ArgumentException;
use Marko\Core\Tests\Unit\Container\Fixtures\T005Child;
use Marko\Core\Tests\Unit\Container\Fixtures\T005Final;
use Marko\Core\Tests\Unit\Container\Fixtures\T005NoLabel;
use Marko\Core\Tests\Unit\Container\Fixtures\T005Original;
use Marko\Core\Tests\Unit\Container\Fixtures\T005Replacement;

it('builds a preference replacement with the replaced class\'s arguments', function (): void {
    $registry = new PreferenceRegistry();
    $registry->register(T005Original::class, T005Replacement::class);
    $container = new Container($registry);
    $container->setArguments(T005Original::class, ['label' => 'inherited']);

    $instance = $container->get(T005Original::class);

    expect($instance)->toBeInstanceOf(T005Replacement::class)
        ->and($instance->label)->toBe('inherited');
});

it('applies inherited arguments when the replacement class is requested directly', function (): void {
    $registry = new PreferenceRegistry();
    $registry->register(T005Original::class, T005Replacement::class);
    $container = new Container($registry);
    $container->setArguments(T005Original::class, ['label' => 'inherited']);

    $instance = $container->get(T005Replacement::class);

    expect($instance->label)->toBe('inherited');
});

it('lets the replacement\'s own arguments override an inherited argument', function (): void {
    $registry = new PreferenceRegistry();
    $registry->register(T005Original::class, T005Replacement::class);
    $container = new Container($registry);
    $container->setArguments(T005Original::class, ['label' => 'inherited']);
    $container->setArguments(T005Replacement::class, ['label' => 'own']);

    expect($container->get(T005Original::class)->label)->toBe('own');
});

it('keeps inherited arguments the replacement does not override', function (): void {
    $registry = new PreferenceRegistry();
    $registry->register(T005Original::class, T005Replacement::class);
    $container = new Container($registry);
    $container->setArguments(T005Original::class, ['label' => 'inherited', 'count' => 9]);
    $container->setArguments(T005Replacement::class, ['label' => 'own']);

    $instance = $container->get(T005Original::class);

    expect($instance->label)->toBe('own')
        ->and($instance->count)->toBe(9);
});

it('merges arguments along a preference chain with later links winning', function (): void {
    $registry = new PreferenceRegistry();
    $registry->register(T005Original::class, T005Replacement::class);
    $registry->register(T005Replacement::class, T005Final::class);
    $container = new Container($registry);
    $container->setArguments(T005Original::class, ['label' => 'first', 'count' => 5]);
    $container->setArguments(T005Replacement::class, ['label' => 'second']);

    $instance = $container->get(T005Original::class);

    expect($instance)->toBeInstanceOf(T005Final::class)
        ->and($instance->label)->toBe('second')
        ->and($instance->count)->toBe(5);
});

it('does not give a plain subclass its parent\'s arguments', function (): void {
    $container = new Container(new PreferenceRegistry());
    $container->setArguments(T005Original::class, ['label' => 'parent']);

    expect($container->get(T005Child::class)->label)->toBe('default');
});

it(
    'names the replaced class when the replacement\'s constructor lacks an inherited parameter name',
    function (): void {
        $registry = new PreferenceRegistry();
        $registry->register(T005Original::class, T005NoLabel::class);
        $container = new Container($registry);
        $container->setArguments(T005Original::class, ['label' => 'inherited']);

        expect(fn () => $container->get(T005Original::class))
            ->toThrow(ArgumentException::class, "replaces '" . T005Original::class . "'");
    },
);
