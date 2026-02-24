<?php

declare(strict_types=1);

use Marko\Pagination\Contracts\CursorInterface;
use Marko\Pagination\Contracts\CursorPaginatorInterface;
use Marko\Pagination\Contracts\PaginatorInterface;

it(
    'defines PaginatorInterface with items, total, perPage, currentPage, lastPage, hasMorePages, previousPage, nextPage, toArray methods',
    function () {
        $reflection = new ReflectionClass(PaginatorInterface::class);
    
        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('items'))->toBeTrue()
            ->and($reflection->hasMethod('total'))->toBeTrue()
            ->and($reflection->hasMethod('perPage'))->toBeTrue()
            ->and($reflection->hasMethod('currentPage'))->toBeTrue()
            ->and($reflection->hasMethod('lastPage'))->toBeTrue()
            ->and($reflection->hasMethod('hasMorePages'))->toBeTrue()
            ->and($reflection->hasMethod('previousPage'))->toBeTrue()
            ->and($reflection->hasMethod('nextPage'))->toBeTrue()
            ->and($reflection->hasMethod('toArray'))->toBeTrue();
    }
);

it(
    'defines CursorPaginatorInterface with items, perPage, hasMorePages, cursor, nextCursor, previousCursor, toArray methods',
    function () {
        $reflection = new ReflectionClass(CursorPaginatorInterface::class);
    
        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('items'))->toBeTrue()
            ->and($reflection->hasMethod('perPage'))->toBeTrue()
            ->and($reflection->hasMethod('hasMorePages'))->toBeTrue()
            ->and($reflection->hasMethod('cursor'))->toBeTrue()
            ->and($reflection->hasMethod('nextCursor'))->toBeTrue()
            ->and($reflection->hasMethod('previousCursor'))->toBeTrue()
            ->and($reflection->hasMethod('toArray'))->toBeTrue();
    }
);

it('defines CursorInterface with parameters, parameter, encode methods and decode static factory', function () {
    $reflection = new ReflectionClass(CursorInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->hasMethod('parameters'))->toBeTrue()
        ->and($reflection->hasMethod('parameter'))->toBeTrue()
        ->and($reflection->hasMethod('encode'))->toBeTrue()
        ->and($reflection->hasMethod('decode'))->toBeTrue()
        ->and($reflection->getMethod('decode')->isStatic())->toBeTrue();
});
