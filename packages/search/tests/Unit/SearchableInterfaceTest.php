<?php

declare(strict_types=1);

use Marko\Search\Contracts\SearchableInterface;

it('defines SearchableInterface for entities declaring searchable fields and weights', function (): void {
    $reflection = new ReflectionClass(SearchableInterface::class);
    $method = $reflection->getMethod('getSearchableFields');
    $params = $method->getParameters();

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->hasMethod('getSearchableFields'))->toBeTrue()
        ->and($params)->toBeEmpty()
        ->and($method->getReturnType()->getName())->toBe('array');

    $entity = new class () implements SearchableInterface
    {
        public function getSearchableFields(): array
        {
            return ['title' => 2.0, 'content' => 1.0, 'tags' => 1.5];
        }
    };

    expect($entity->getSearchableFields())->toBe(['title' => 2.0, 'content' => 1.0, 'tags' => 1.5]);
});
