<?php

declare(strict_types=1);

use Marko\Search\Contracts\SearchInterface;
use Marko\Search\Value\SearchCriteria;
use Marko\Search\Value\SearchResult;

it('defines SearchInterface with search method accepting query and criteria', function (): void {
    $reflection = new ReflectionClass(SearchInterface::class);
    $method = $reflection->getMethod('search');
    $params = $method->getParameters();

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->hasMethod('search'))->toBeTrue()
        ->and($params)->toHaveCount(2)
        ->and($params[0]->getName())->toBe('query')
        ->and($params[0]->getType()->getName())->toBe('string')
        ->and($params[1]->getName())->toBe('criteria')
        ->and($params[1]->getType()->getName())->toBe(SearchCriteria::class)
        ->and($method->getReturnType()->getName())->toBe(SearchResult::class);
});
