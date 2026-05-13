<?php

declare(strict_types=1);

use Marko\Scope\Query\ScopeSortExpression;
use Marko\Scope\Query\ScopeSortRendererInterface;

it('documents that renderers must produce a COALESCE expression matching the walker order', function (): void {
    $reflection = new ReflectionClass(ScopeSortRendererInterface::class);
    $docComment = $reflection->getDocComment();

    expect($docComment)->toBeString()
        ->and($docComment)->toContain('COALESCE')
        ->and($docComment)->toContain('walker');
});

it('defines ScopeSortRendererInterface with render(ScopeSortExpression) returning a SQL fragment', function (): void {
    $renderer = new class () implements ScopeSortRendererInterface
    {
        public function render(ScopeSortExpression $expression): string
        {
            return 'COALESCE(json_extract(scopes, "$.store.price"), price) ASC';
        }
    };

    $expression = new ScopeSortExpression(
        property: 'price',
        column: 'price',
        paths: [['axis' => 'store', 'path' => 'store']],
        direction: 'asc',
    );

    $sql = $renderer->render($expression);

    expect($sql)->toBeString()
        ->and($sql)->not->toBeEmpty();
});
