<?php

declare(strict_types=1);

use Marko\Docs\Contract\DocsSearchInterface;
use Marko\Docs\Exceptions\DocsException;
use Marko\Docs\ValueObject\DocsNavEntry;
use Marko\Docs\ValueObject\DocsPage;
use Marko\Docs\ValueObject\DocsQuery;
use Marko\Docs\ValueObject\DocsResult;

it('has composer.json with name marko/docs and PSR-4 namespace Marko\\Docs\\', function (): void {
    $composer = json_decode(
        (string) file_get_contents(__DIR__ . '/../../composer.json'),
        true,
    );

    expect($composer)->toBeArray()
        ->and($composer['name'])->toBe('marko/docs')
        ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\Docs\\')
        ->and($composer['autoload']['psr-4']['Marko\\Docs\\'])->toBe('src/');
});

it('defines DocsSearchInterface with search query limit, getPage id, listNav methods', function (): void {
    $reflection = new ReflectionClass(DocsSearchInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->hasMethod('search'))->toBeTrue()
        ->and($reflection->hasMethod('getPage'))->toBeTrue()
        ->and($reflection->hasMethod('listNav'))->toBeTrue()
        ->and($reflection->hasMethod('driverName'))->toBeTrue();

    $search = $reflection->getMethod('search');
    expect($search->getNumberOfParameters())->toBe(1)
        ->and($search->getParameters()[0]->getType()?->getName())->toBe(DocsQuery::class);

    $getPage = $reflection->getMethod('getPage');
    expect($getPage->getParameters()[0]->getName())->toBe('id')
        ->and($getPage->getParameters()[0]->getType()?->getName())->toBe('string');
});

it('defines readonly DocsQuery value object with query text and limit', function (): void {
    $query = new DocsQuery(query: 'how to install', limit: 5);
    $reflection = new ReflectionClass(DocsQuery::class);

    expect($reflection->isReadOnly())->toBeTrue()
        ->and($query->query)->toBe('how to install')
        ->and($query->limit)->toBe(5)
        ->and((new DocsQuery('x'))->limit)->toBe(10);
});

it('defines readonly DocsResult value object with pageId title excerpt score', function (): void {
    $result = new DocsResult(
        pageId: 'getting-started/installation',
        title: 'Installation',
        excerpt: 'How to install Marko...',
        score: 0.92,
    );
    $reflection = new ReflectionClass(DocsResult::class);

    expect($reflection->isReadOnly())->toBeTrue()
        ->and($result->pageId)->toBe('getting-started/installation')
        ->and($result->title)->toBe('Installation')
        ->and($result->excerpt)->toBe('How to install Marko...')
        ->and($result->score)->toBe(0.92);
});

it('defines readonly DocsPage value object with id title content path', function (): void {
    $page = new DocsPage(
        id: 'guides/routing',
        title: 'Routing',
        content: '# Routing\n\nMarko uses attribute-based routes.',
        path: 'guides/routing.md',
    );
    $reflection = new ReflectionClass(DocsPage::class);

    expect($reflection->isReadOnly())->toBeTrue()
        ->and($page->id)->toBe('guides/routing')
        ->and($page->title)->toBe('Routing')
        ->and($page->path)->toBe('guides/routing.md');
});

it('defines DocsException with contextual error factories for page-not-found and search-failure', function (): void {
    $notFound = DocsException::pageNotFound('missing/page');
    $searchFailure = DocsException::searchFailed('database locked');

    expect($notFound)->toBeInstanceOf(DocsException::class)
        ->and($notFound->getMessage())->toContain('missing/page')
        ->and($notFound->getContext())->not->toBeEmpty()
        ->and($notFound->getSuggestion())->not->toBeEmpty()
        ->and($searchFailure)->toBeInstanceOf(DocsException::class)
        ->and($searchFailure->getMessage())->toContain('database locked');
});

it('has no runtime classes beyond value objects and interfaces', function (): void {
    $srcDir = __DIR__ . '/../../src';
    $allowedDirs = ['Contract', 'ValueObject', 'Exceptions'];

    $items = array_filter(
        scandir($srcDir) ?: [],
        fn (string $entry): bool => $entry !== '.' && $entry !== '..',
    );

    foreach ($items as $item) {
        expect($allowedDirs)->toContain($item);
    }

    expect(new DocsNavEntry(id: 'a', title: 'A', path: 'a'))->toBeInstanceOf(DocsNavEntry::class);
});
