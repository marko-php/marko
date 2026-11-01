<?php

declare(strict_types=1);

use Marko\View\Exceptions\ViewProviderException;
use Marko\View\TemplateFunction;

describe('TemplateFunction', function (): void {
    it('holds the name and callable and escapes output by default', function (): void {
        $callable = fn (): string => 'x';
        $function = new TemplateFunction('route', $callable);

        expect($function->name)->toBe('route')
            ->and($function->callable)->toBe($callable)
            ->and($function->isSafeHtml)->toBeFalse();
    });

    it('can declare its output as safe HTML', function (): void {
        $function = new TemplateFunction('csrf_field', fn (): string => '<input>', isSafeHtml: true);

        expect($function->isSafeHtml)->toBeTrue();
    });

    it('rejects a name that is not a valid function identifier', function (string $name): void {
        new TemplateFunction($name, fn (): string => '');
    })->throws(ViewProviderException::class, 'is not a valid template function name')
        ->with(['', '1route', 'my-route', 'route()', 'a b', '_private']);
});
