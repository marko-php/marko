<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Services;

use Marko\Blog\Services\SlugGenerator;
use Marko\Blog\Services\SlugGeneratorInterface;

it('converts title to lowercase slug', function (): void {
    $generator = new SlugGenerator();

    expect($generator)->toBeInstanceOf(SlugGeneratorInterface::class)
        ->and($generator->generate(title: 'Hello World'))->toBe('hello-world');
});

it('replaces spaces with hyphens', function (): void {
    $generator = new SlugGenerator();

    expect($generator->generate(title: 'My Blog Post Title'))->toBe('my-blog-post-title')
        ->and($generator->generate(title: 'Multiple   Spaces'))->toBe('multiple-spaces');
});

it('removes special characters except hyphens', function (): void {
    $generator = new SlugGenerator();

    expect($generator->generate(title: 'Hello, World!'))->toBe('hello-world')
        ->and($generator->generate(title: 'What?! Really...'))->toBe('what-really')
        ->and($generator->generate(title: 'Price: $100'))->toBe('price-100')
        ->and($generator->generate(title: "It's a Test"))->toBe('its-a-test')
        ->and($generator->generate(title: 'A-B-C'))->toBe('a-b-c');
});

it('collapses multiple hyphens into single hyphen', function (): void {
    $generator = new SlugGenerator();

    expect($generator->generate(title: 'One & Two'))->toBe('one-two')
        ->and($generator->generate(title: 'Hello---World'))->toBe('hello-world')
        ->and($generator->generate(title: 'A - - B'))->toBe('a-b');
});

it('trims hyphens from start and end', function (): void {
    $generator = new SlugGenerator();

    expect($generator->generate(title: '-Hello World'))->toBe('hello-world')
        ->and($generator->generate(title: 'Hello World-'))->toBe('hello-world')
        ->and($generator->generate(title: '--Hello World--'))->toBe('hello-world')
        ->and($generator->generate(title: '---Test---'))->toBe('test')
        ->and($generator->generate(title: ' Hello World '))->toBe('hello-world');
});

it('handles unicode characters by transliterating to ASCII', function (): void {
    $generator = new SlugGenerator();

    expect($generator->generate(title: 'Café'))->toBe('cafe')
        ->and($generator->generate(title: 'Müller'))->toBe('muller')
        ->and($generator->generate(title: 'Niño'))->toBe('nino')
        ->and($generator->generate(title: 'Français'))->toBe('francais')
        ->and($generator->generate(title: 'Résumé'))->toBe('resume');
});

it('generates unique slug by appending number when duplicate exists', function (): void {
    $generator = new SlugGenerator();
    $existingSlugs = ['hello-world', 'hello-world-1', 'hello-world-2'];

    $uniquenessChecker = fn (string $slug): bool => !in_array($slug, $existingSlugs, strict: true);

    $slug = $generator->generate(
        title: 'Hello World',
        uniquenessChecker: $uniquenessChecker,
    );

    expect($slug)->toBe('hello-world-3');
});

it('accepts custom uniqueness checker callback', function (): void {
    $generator = new SlugGenerator();
    $checkedSlugs = [];

    $customChecker = function (string $slug) use (&$checkedSlugs): bool {
        $checkedSlugs[] = $slug;

        return $slug === 'my-post-2';
    };

    $slug = $generator->generate(
        title: 'My Post',
        uniquenessChecker: $customChecker,
    );

    expect($slug)->toBe('my-post-2')
        ->and($checkedSlugs)->toBe(['my-post', 'my-post-1', 'my-post-2']);
});
