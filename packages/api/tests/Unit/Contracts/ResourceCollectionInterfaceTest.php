<?php

declare(strict_types=1);

use Marko\Api\Contracts\ResourceCollectionInterface;
use Marko\Pagination\Contracts\PaginatorInterface;
use Marko\Routing\Http\Response;

it('defines ResourceCollectionInterface with toArray, toResponse, and withPagination methods', function () {
    expect(interface_exists(ResourceCollectionInterface::class))->toBeTrue()
        ->and(method_exists(ResourceCollectionInterface::class, 'toArray'))->toBeTrue()
        ->and(method_exists(ResourceCollectionInterface::class, 'toResponse'))->toBeTrue()
        ->and(method_exists(ResourceCollectionInterface::class, 'withPagination'))->toBeTrue();
});

it('requires toArray to return an array', function () {
    $reflection = new ReflectionMethod(ResourceCollectionInterface::class, 'toArray');

    expect($reflection->getReturnType()?->getName())->toBe('array');
});

it('requires toResponse to return a Response', function () {
    $reflection = new ReflectionMethod(ResourceCollectionInterface::class, 'toResponse');

    expect($reflection->getReturnType()?->getName())->toBe(Response::class);
});

it('requires withPagination to accept a PaginatorInterface and return static', function () {
    $reflection = new ReflectionMethod(ResourceCollectionInterface::class, 'withPagination');
    $params = $reflection->getParameters();

    expect($params)->toHaveCount(1)
        ->and($params[0]->getType()?->getName())->toBe(PaginatorInterface::class)
        ->and($reflection->getReturnType()?->getName())->toBe('static');
});
