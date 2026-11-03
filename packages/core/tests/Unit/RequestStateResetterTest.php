<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Contracts\ResettableInterface;
use Marko\Core\Plugin\InterceptorClassGenerator;
use Marko\Core\Plugin\PluginDefinition;
use Marko\Core\Plugin\PluginInterceptedInterface;
use Marko\Core\Plugin\PluginInterceptor;
use Marko\Core\Plugin\PluginRegistry;
use Marko\Core\RequestStateResetter;

interface ResetterCurrentUserInterface
{
    public function name(): ?string;

    public function remember(
        string $name,
    ): void;
}

/**
 * Holds per-request state behind an interface, the way SessionGuard does.
 */
class ResetterCurrentUser implements ResetterCurrentUserInterface, ResettableInterface
{
    private ?string $name = null;

    public function name(): ?string
    {
        return $this->name;
    }

    public function remember(
        string $name,
    ): void {
        $this->name = $name;
    }

    public function reset(): void
    {
        $this->name = null;
    }
}

class ResetterCurrentUserPlugin
{
    public function beforeName(): ?array
    {
        return null;
    }
}

/**
 * A container that wraps the shared $targetClass service in a plugin proxy:
 * an interface wrapper for an interface, a subclass for a class.
 *
 * @param class-string $targetClass
 */
function resetterContainerPlugging(
    string $targetClass,
): Container {
    $container = new Container();
    $registry = new PluginRegistry();
    $registry->register(new PluginDefinition(
        pluginClass: ResetterCurrentUserPlugin::class,
        targetClass: $targetClass,
        beforeMethods: ['name' => ['pluginMethod' => 'beforeName', 'sortOrder' => 10]],
    ));
    $container->setPluginInterceptor(
        new PluginInterceptor($container, $registry, new InterceptorClassGenerator()),
    );
    $container->bind(ResetterCurrentUserInterface::class, ResetterCurrentUser::class);
    $container->singleton($targetClass);

    return $container;
}

function requestStateResetterSpy(
    array &$log,
    string $label,
): ResettableInterface {
    return new class ($log, $label) implements ResettableInterface
    {
        /**
         * @param array<string> $log
         */
        public function __construct(
            private array &$log,
            private readonly string $label,
        ) {}

        public function reset(): void
        {
            $this->log[] = $this->label;
        }
    };
}

describe('RequestStateResetter', function (): void {
    it('resets every resolved ResettableInterface instance', function (): void {
        $log = [];
        $container = new Container();
        $container->instance('first', requestStateResetterSpy($log, 'first'));
        $container->instance('second', requestStateResetterSpy($log, 'second'));

        new RequestStateResetter($container)->reset();

        expect($log)->toEqualCanonicalizing(['first', 'second']);
    });

    it('does not touch resolved instances that are not resettable', function (): void {
        $container = new Container();
        $plain = new stdClass();
        $plain->touched = false;
        $container->instance('plain', $plain);

        new RequestStateResetter($container)->reset();

        expect($plain->touched)->toBeFalse();
    });

    it('resets in ascending binding id order', function (): void {
        $log = [];
        $container = new Container();
        $container->instance('Zeta\Service', requestStateResetterSpy($log, 'zeta'));
        $container->instance('Alpha\Service', requestStateResetterSpy($log, 'alpha'));
        $container->instance('Mid\Service', requestStateResetterSpy($log, 'mid'));

        new RequestStateResetter($container)->reset();

        expect($log)->toBe(['alpha', 'mid', 'zeta']);
    });

    it('lets a reset failure propagate', function (): void {
        $container = new Container();
        $container->instance('faulty', new class () implements ResettableInterface
        {
            public function reset(): void
            {
                throw new RuntimeException('reset failed');
            }
        });

        new RequestStateResetter($container)->reset();
    })->throws(RuntimeException::class, 'reset failed');
    it('resets a resettable service that a plugin interface proxy wraps', function (): void {
        $container = resetterContainerPlugging(ResetterCurrentUserInterface::class);
        $user = $container->get(ResetterCurrentUserInterface::class);
        $user->remember('Etienne Aaron Scherrer');

        new RequestStateResetter($container)->reset();

        expect($user)->toBeInstanceOf(PluginInterceptedInterface::class)
            ->and($user->name())->toBeNull();
    });

    it('resets a resettable service that a plugin subclass proxy replaces', function (): void {
        $container = resetterContainerPlugging(ResetterCurrentUser::class);
        $user = $container->get(ResetterCurrentUser::class);
        $user->remember('Etienne Aaron Scherrer');

        new RequestStateResetter($container)->reset();

        expect($user)->toBeInstanceOf(PluginInterceptedInterface::class)
            ->and($user->name())->toBeNull();
    });

    it('resets a resettable service behind a proxy that wraps another proxy', function (): void {
        $container = new Container();
        $registry = new PluginRegistry();
        $registry->register(new PluginDefinition(
            pluginClass: ResetterCurrentUserPlugin::class,
            targetClass: ResetterCurrentUserInterface::class,
            beforeMethods: ['name' => ['pluginMethod' => 'beforeName', 'sortOrder' => 10]],
        ));
        $interceptor = new PluginInterceptor($container, $registry, new InterceptorClassGenerator());
        $user = new ResetterCurrentUser();
        $inner = $interceptor->createProxy(ResetterCurrentUserInterface::class, ResetterCurrentUser::class, $user);
        $outer = $interceptor->createProxy(ResetterCurrentUserInterface::class, $inner::class, $inner);
        $container->instance(ResetterCurrentUserInterface::class, $outer);
        $user->remember('Etienne Aaron Scherrer');

        new RequestStateResetter($container)->reset();

        expect($outer->getPluginTarget())->toBe($inner)
            ->and($user->name())->toBeNull();
    });

    it('skips a wrapped service passed as an exception', function (): void {
        $container = resetterContainerPlugging(ResetterCurrentUserInterface::class);
        $user = $container->get(ResetterCurrentUserInterface::class);
        $user->remember('Kira Spielmann');

        new RequestStateResetter($container)->reset($user->getPluginTarget());

        expect($user->name())->toBe('Kira Spielmann');
    });

    it('skips the instances passed as exceptions', function (): void {
        $log = [];
        $container = new Container();
        $kept = requestStateResetterSpy($log, 'kept');
        $container->instance('kept', $kept);
        $container->instance('kept-alias', $kept);
        $container->instance('other', requestStateResetterSpy($log, 'other'));

        new RequestStateResetter($container)->reset($kept);

        expect($log)->toBe(['other']);
    });
});
