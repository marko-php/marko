<?php

declare(strict_types=1);

use Marko\Layout\Attributes\Component;
use Marko\Layout\Attributes\Layout;
use Marko\Layout\Exceptions\LayoutNotFoundException;
use Marko\Layout\LayoutResolver;

// Fixtures defined once for all tests
#[Layout(component: FixtureRootComponent::class)]
class FixtureClassLevelController
{
    public function index(): void {}
}

#[Layout(component: FixtureRootComponent::class)]
class FixtureClassAndMethodController
{
    #[Layout(component: FixtureMethodComponent::class)]
    public function show(): void {}
}

class FixtureMethodOnlyController
{
    #[Layout(component: FixtureRootComponent::class)]
    public function show(): void {}
}

class FixtureNoLayoutController
{
    public function index(): void {}
}

class FixtureNoComponentController
{
    public function index(): void {}
}

#[Layout(component: FixtureNoComponentClass::class)]
class FixtureLayoutWithNoComponentController
{
    public function index(): void {}
}

#[Component(template: 'root.html')]
class FixtureRootComponent {}

#[Component(template: 'method.html')]
class FixtureMethodComponent {}

class FixtureNoComponentClass {}

describe('LayoutResolver', function (): void {
    it('resolves layout from class-level attribute', function (): void {
        $resolver = new LayoutResolver();

        $result = $resolver->resolve(FixtureClassLevelController::class, 'index');

        expect($result['componentClass'])->toBe(FixtureRootComponent::class);
    });

    it('resolves layout from method-level attribute', function (): void {
        $resolver = new LayoutResolver();

        $result = $resolver->resolve(FixtureMethodOnlyController::class, 'show');

        expect($result['componentClass'])->toBe(FixtureRootComponent::class);
    });

    it('prefers method-level layout over class-level layout', function (): void {
        $resolver = new LayoutResolver();

        $result = $resolver->resolve(FixtureClassAndMethodController::class, 'show');

        expect($result['componentClass'])->toBe(FixtureMethodComponent::class);
    });

    it('throws LayoutNotFoundException when no layout attribute exists', function (): void {
        $resolver = new LayoutResolver();

        expect(fn () => $resolver->resolve(FixtureNoLayoutController::class, 'index'))
            ->toThrow(LayoutNotFoundException::class);
    });

    it('throws LayoutNotFoundException when referenced class has no Component attribute', function (): void {
        $resolver = new LayoutResolver();

        expect(fn () => $resolver->resolve(FixtureLayoutWithNoComponentController::class, 'index'))
            ->toThrow(LayoutNotFoundException::class);
    });

    it('returns the component class name and its Component attribute', function (): void {
        $resolver = new LayoutResolver();

        $result = $resolver->resolve(FixtureClassLevelController::class, 'index');

        expect($result['componentClass'])->toBe(FixtureRootComponent::class)
            ->and($result['attribute'])->toBeInstanceOf(Component::class)
            ->and($result['attribute']->template)->toBe('root.html');
    });
});
