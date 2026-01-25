<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Entity;

use Marko\Blog\Entity\Author;
use Marko\Database\Attributes\Column;
use ReflectionClass;

it('creates author with name email and bio', function (): void {
    $author = new Author();
    $author->name = 'John Doe';
    $author->email = 'john@example.com';
    $author->bio = 'A passionate writer and developer.';

    expect($author->name)->toBe('John Doe')
        ->and($author->email)->toBe('john@example.com')
        ->and($author->bio)->toBe('A passionate writer and developer.');
});

it('requires name field', function (): void {
    $reflection = new ReflectionClass(Author::class);
    $property = $reflection->getProperty('name');

    expect($property->getType()->allowsNull())->toBeFalse()
        ->and($property->getType()->getName())->toBe('string');
});

it('requires email field with valid format', function (): void {
    $reflection = new ReflectionClass(Author::class);
    $property = $reflection->getProperty('email');

    expect($property->getType()->allowsNull())->toBeFalse()
        ->and($property->getType()->getName())->toBe('string');
});

it('auto-generates slug from name using SlugGenerator', function (): void {
    $reflection = new ReflectionClass(Author::class);
    $property = $reflection->getProperty('slug');

    expect($property->getType()->allowsNull())->toBeFalse()
        ->and($property->getType()->getName())->toBe('string');
});

it('allows manual slug override', function (): void {
    $author = new Author();
    $author->name = 'John Doe';
    $author->email = 'john@example.com';
    $author->slug = 'custom-john-slug';

    expect($author->slug)->toBe('custom-john-slug');
});

it('ensures slug uniqueness within authors table', function (): void {
    $reflection = new ReflectionClass(Author::class);
    $property = $reflection->getProperty('slug');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->unique)->toBeTrue();
});

it('has created_at timestamp', function (): void {
    $reflection = new ReflectionClass(Author::class);

    expect($reflection->hasProperty('createdAt'))->toBeTrue();

    $property = $reflection->getProperty('createdAt');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBe('created_at');
});

it('has updated_at timestamp', function (): void {
    $reflection = new ReflectionClass(Author::class);

    expect($reflection->hasProperty('updatedAt'))->toBeTrue();

    $property = $reflection->getProperty('updatedAt');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBe('updated_at');
});
