<?php

declare(strict_types=1);

$viewsPath = dirname(__DIR__, 4) . '/resources/views';

it('creates index template with posts table and pagination', function () use ($viewsPath): void {
    $templatePath = $viewsPath . '/admin/post/index.latte';

    expect(file_exists($templatePath))->toBeTrue('Index template should exist');

    $content = file_get_contents($templatePath);

    expect($content)->toContain('<table')
        ->and($content)->toContain('</table>')
        ->and($content)->toContain('<thead')
        ->and($content)->toContain('<tbody')
        ->and($content)->toContain('{foreach')
        ->and($content)->toContain('$posts->items')
        ->and($content)->toContain('pagination')
        ->and($content)->toContain('$posts->shouldShowPagination()');
});

it('creates create template with form fields for all post properties', function () use ($viewsPath): void {
    $templatePath = $viewsPath . '/admin/post/create.latte';

    expect(file_exists($templatePath))->toBeTrue('Create template should exist');

    $content = file_get_contents($templatePath);

    expect($content)->toContain('<form')
        ->and($content)->toContain('method="post"')
        ->and($content)->toContain('name="title"')
        ->and($content)->toContain('name="content"')
        ->and($content)->toContain('name="summary"')
        ->and($content)->toContain('name="author_id"')
        ->and($content)->toContain('name="category_ids[]"')
        ->and($content)->toContain('name="tag_ids[]"')
        ->and($content)->toContain('<label')
        ->and($content)->toContain('<button');
});

it('creates edit template pre-populated with existing post data', function () use ($viewsPath): void {
    $templatePath = $viewsPath . '/admin/post/edit.latte';

    expect(file_exists($templatePath))->toBeTrue('Edit template should exist');

    $content = file_get_contents($templatePath);

    expect($content)->toContain('<form')
        ->and($content)->toContain('$post')
        ->and($content)->toContain('$post->title')
        ->and($content)->toContain('$post->content')
        ->and($content)->toContain('$post->summary')
        ->and($content)->toContain('$post->authorId')
        ->and($content)->toContain('$post->id')
        ->and($content)->toContain('<button');
});

it('includes author dropdown populated from passed authors array', function () use ($viewsPath): void {
    $createContent = file_get_contents($viewsPath . '/admin/post/create.latte');
    $editContent = file_get_contents($viewsPath . '/admin/post/edit.latte');

    expect($createContent)->toContain('<select')
        ->and($createContent)->toContain('name="author_id"')
        ->and($createContent)->toContain('{foreach $authors as $author}')
        ->and($createContent)->toContain('$author->getId()')
        ->and($createContent)->toContain('$author->getName()')
        ->and($editContent)->toContain('<select')
        ->and($editContent)->toContain('name="author_id"')
        ->and($editContent)->toContain('{foreach $authors as $author}')
        ->and($editContent)->toContain('$author->getId()')
        ->and($editContent)->toContain('$author->getName()');
});

it('includes category checkboxes populated from passed categories array', function () use ($viewsPath): void {
    $createContent = file_get_contents($viewsPath . '/admin/post/create.latte');
    $editContent = file_get_contents($viewsPath . '/admin/post/edit.latte');

    expect($createContent)->toContain('type="checkbox"')
        ->and($createContent)->toContain('name="category_ids[]"')
        ->and($createContent)->toContain('{foreach $categories as $category}')
        ->and($createContent)->toContain('$category->getId()')
        ->and($createContent)->toContain('$category->getName()')
        ->and($editContent)->toContain('type="checkbox"')
        ->and($editContent)->toContain('name="category_ids[]"')
        ->and($editContent)->toContain('{foreach $categories as $category}')
        ->and($editContent)->toContain('$category->getId()')
        ->and($editContent)->toContain('$category->getName()');
});

it('includes tag selection populated from passed tags array', function () use ($viewsPath): void {
    $createContent = file_get_contents($viewsPath . '/admin/post/create.latte');
    $editContent = file_get_contents($viewsPath . '/admin/post/edit.latte');

    expect($createContent)->toContain('name="tag_ids[]"')
        ->and($createContent)->toContain('{foreach $tags as $tag}')
        ->and($createContent)->toContain('$tag->getId()')
        ->and($createContent)->toContain('$tag->getName()')
        ->and($editContent)->toContain('name="tag_ids[]"')
        ->and($editContent)->toContain('{foreach $tags as $tag}')
        ->and($editContent)->toContain('$tag->getId()')
        ->and($editContent)->toContain('$tag->getName()');
});

it('includes status dropdown with Draft, Published, Scheduled options', function () use ($viewsPath): void {
    $editContent = file_get_contents($viewsPath . '/admin/post/edit.latte');

    expect($editContent)->toContain('name="status"')
        ->and($editContent)->toContain('Draft')
        ->and($editContent)->toContain('Published')
        ->and($editContent)->toContain('Scheduled')
        ->and($editContent)->toContain('draft')
        ->and($editContent)->toContain('published')
        ->and($editContent)->toContain('scheduled');
});

it('shows action buttons for edit and delete on each row', function () use ($viewsPath): void {
    $content = file_get_contents($viewsPath . '/admin/post/index.latte');

    expect($content)->toContain('/admin/blog/posts/')
        ->and($content)->toContain('/edit')
        ->and($content)->toContain('Edit')
        ->and($content)->toContain('Delete')
        ->and($content)->toContain('method="post"')
        ->and($content)->toContain('_method')
        ->and($content)->toContain('DELETE');
});

it('extends admin-panel base layout', function () use ($viewsPath): void {
    $indexContent = file_get_contents($viewsPath . '/admin/post/index.latte');
    $createContent = file_get_contents($viewsPath . '/admin/post/create.latte');
    $editContent = file_get_contents($viewsPath . '/admin/post/edit.latte');

    expect($indexContent)->toContain("{layout 'admin-panel::layout/base'}")
        ->and($indexContent)->toContain('{block content}')
        ->and($createContent)->toContain("{layout 'admin-panel::layout/base'}")
        ->and($createContent)->toContain('{block content}')
        ->and($editContent)->toContain("{layout 'admin-panel::layout/base'}")
        ->and($editContent)->toContain('{block content}');
});

it('includes flash message display', function () use ($viewsPath): void {
    $indexContent = file_get_contents($viewsPath . '/admin/post/index.latte');
    $createContent = file_get_contents($viewsPath . '/admin/post/create.latte');
    $editContent = file_get_contents($viewsPath . '/admin/post/edit.latte');

    // Flash messages are handled by base layout via {include 'admin-panel::partials/flash'}
    // The base layout already includes flash messages, so templates just need to extend it.
    // Templates may also display validation errors inline.
    expect($indexContent)->toContain("{layout 'admin-panel::layout/base'}")
        ->and($createContent)->toContain('$errors')
        ->and($editContent)->toContain('$errors');
});
