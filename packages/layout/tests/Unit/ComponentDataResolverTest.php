<?php

declare(strict_types=1);

use Marko\Layout\ComponentDataResolver;
use Marko\Routing\Http\Request;

it('returns empty array when component has no data method', function (): void {
    $resolver = new ComponentDataResolver();
    $component = new class () {};
    $request = new Request();

    $result = $resolver->resolve($component, [], $request);

    expect($result)->toBe([]);
});

it('calls data method with no arguments when method has no parameters', function (): void {
    $resolver = new ComponentDataResolver();
    $component = new class ()
    {
        public function data(): array
        {
            return ['called' => true];
        }
    };
    $request = new Request();

    $result = $resolver->resolve($component, [], $request);

    expect($result)->toBe(['called' => true]);
});

it('injects route parameters by name into data method', function (): void {
    $resolver = new ComponentDataResolver();
    $component = new class ()
    {
        public function data(string $slug): array
        {
            return ['slug' => $slug];
        }
    };
    $request = new Request();

    $result = $resolver->resolve($component, ['slug' => 'hello-world'], $request);

    expect($result)->toBe(['slug' => 'hello-world']);
});

it('casts route parameters to int when type-hinted', function (): void {
    $resolver = new ComponentDataResolver();
    $component = new class ()
    {
        public function data(int $id): array
        {
            return ['id' => $id];
        }
    };
    $request = new Request();

    $result = $resolver->resolve($component, ['id' => '42'], $request);

    expect($result)->toBe(['id' => 42]);
});

it('casts route parameters to float when type-hinted', function (): void {
    $resolver = new ComponentDataResolver();
    $component = new class ()
    {
        public function data(float $price): array
        {
            return ['price' => $price];
        }
    };
    $request = new Request();

    $result = $resolver->resolve($component, ['price' => '9.99'], $request);

    expect($result)->toBe(['price' => 9.99]);
});

it('casts route parameters to bool when type-hinted', function (): void {
    $resolver = new ComponentDataResolver();
    $component = new class ()
    {
        public function data(bool $active): array
        {
            return ['active' => $active];
        }
    };
    $request = new Request();

    $result = $resolver->resolve($component, ['active' => '1'], $request);

    expect($result)->toBe(['active' => true]);
});

it('injects Request object when type-hinted', function (): void {
    $resolver = new ComponentDataResolver();
    $component = new class ()
    {
        public function data(Request $request): array
        {
            return ['method' => $request->method()];
        }
    };
    $request = new Request(server: ['REQUEST_METHOD' => 'GET']);

    $result = $resolver->resolve($component, [], $request);

    expect($result)->toBe(['method' => 'GET']);
});

it('uses default values when route parameter is not available', function (): void {
    $resolver = new ComponentDataResolver();
    $component = new class ()
    {
        public function data(string $color = 'blue'): array
        {
            return ['color' => $color];
        }
    };
    $request = new Request();

    $result = $resolver->resolve($component, [], $request);

    expect($result)->toBe(['color' => 'blue']);
});

it('returns the array returned by the data method', function (): void {
    $resolver = new ComponentDataResolver();
    $component = new class ()
    {
        public function data(): array
        {
            return ['foo' => 'bar', 'baz' => 42];
        }
    };
    $request = new Request();

    $result = $resolver->resolve($component, [], $request);

    expect($result)->toBe(['foo' => 'bar', 'baz' => 42]);
});
