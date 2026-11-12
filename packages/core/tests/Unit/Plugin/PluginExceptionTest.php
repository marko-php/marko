<?php

declare(strict_types=1);

use Marko\Core\Exceptions\PluginException;

describe('cannotInterceptReadonly', function (): void {
    it('creates cannotInterceptReadonly exception with class name in message', function (): void {
        $exception = PluginException::cannotInterceptReadonly('App\Models\User');

        expect($exception->getMessage())->toContain('App\Models\User');
    });

    it('includes suggestion to target the interface instead in cannotInterceptReadonly', function (): void {
        $exception = PluginException::cannotInterceptReadonly('App\Models\User');

        expect($exception->getMessage())->toContain('App\Models\User')
            ->and($exception->getSuggestion())->toContain('interface');
    });

    it('creates cannotInterceptReadonly exception that is instance of PluginException', function (): void {
        $exception = PluginException::cannotInterceptReadonly('App\Models\User');

        expect($exception)->toBeInstanceOf(PluginException::class);
    });
});

describe('final targets', function (): void {
    it('suggests targeting an interface when a plugin targets a final class', function (): void {
        $exception = PluginException::cannotTargetFinalClass('App\\Plugin\\FooPlugin', 'Vendor\\Foo');

        expect($exception)->toBeInstanceOf(PluginException::class)
            ->and($exception->getMessage())->toContain('App\\Plugin\\FooPlugin')
            ->and($exception->getMessage())->toContain('Vendor\\Foo')
            ->and($exception->getSuggestion())->toContain('interface');
    });

    it('names the final method a plugin tried to hook', function (): void {
        $exception = PluginException::cannotTargetFinalMethod('App\\Plugin\\FooPlugin', 'Vendor\\Foo', 'bar');

        expect($exception->getMessage())->toContain('Vendor\\Foo::bar()')
            ->and($exception->getMessage())->toContain('App\\Plugin\\FooPlugin')
            ->and($exception->getSuggestion())->toContain('interface');
    });

    it('suggests targeting an interface when an interceptor would extend a final class', function (): void {
        $exception = PluginException::cannotInterceptFinal('Vendor\\Foo');

        expect($exception->getMessage())->toContain('Vendor\\Foo')
            ->and($exception->getSuggestion())->toContain('interface');
    });
});

describe('ambiguousInterfacePlugins', function (): void {
    it('creates ambiguousInterfacePlugins exception listing the conflicting interfaces', function (): void {
        $exception = PluginException::ambiguousInterfacePlugins(
            'App\Models\User',
            ['App\Contracts\UserInterface', 'App\Contracts\AuthInterface'],
        );

        expect($exception->getMessage())->toContain('App\Contracts\UserInterface')
            ->and($exception->getMessage())->toContain('App\Contracts\AuthInterface');
    });

    it('includes suggestion to target the concrete class directly in ambiguousInterfacePlugins', function (): void {
        $exception = PluginException::ambiguousInterfacePlugins(
            'App\Models\User',
            ['App\Contracts\UserInterface'],
        );

        expect($exception->getSuggestion())->toContain('App\Models\User');
    });

    it('creates ambiguousInterfacePlugins exception that is instance of PluginException', function (): void {
        $exception = PluginException::ambiguousInterfacePlugins(
            'App\Models\User',
            ['App\Contracts\UserInterface'],
        );

        expect($exception)->toBeInstanceOf(PluginException::class);
    });
});

describe('unsupportedDefaultValue', function (): void {
    it('names the method, parameter and object class in unsupportedDefaultValue', function (): void {
        $exception = PluginException::unsupportedDefaultValue('App\Service::run', 'clock', 'App\Clock');

        expect($exception->getMessage())->toContain("'App\Service::run()'")
            ->and($exception->getMessage())->toContain('$clock')
            ->and($exception->getMessage())->toContain('App\Clock')
            ->and($exception->getContext())->toContain('App\Service::run()');
    });

    it('suggests a nullable parameter with a null default in unsupportedDefaultValue', function (): void {
        $exception = PluginException::unsupportedDefaultValue('App\Service::run', 'clock', 'App\Clock');

        expect($exception->getSuggestion())->toContain('?App\Clock $clock = null')
            ->and($exception->getSuggestion())->toContain('$clock ??= new App\Clock()');
    });
});
