<?php

declare(strict_types=1);

$viewsPath = dirname(__DIR__, 4) . '/resources/views';

// Author templates

it('creates author index, create, and edit templates', function () use ($viewsPath): void {
    $indexPath = $viewsPath . '/admin/author/index.latte';
    $createPath = $viewsPath . '/admin/author/create.latte';
    $editPath = $viewsPath . '/admin/author/edit.latte';

    expect(file_exists($indexPath))->toBeTrue('Author index template should exist')
        ->and(file_exists($createPath))->toBeTrue('Author create template should exist')
        ->and(file_exists($editPath))->toBeTrue('Author edit template should exist');

    $indexContent = file_get_contents($indexPath);
    $createContent = file_get_contents($createPath);
    $editContent = file_get_contents($editPath);

    // Index has table with author fields
    expect($indexContent)->toContain('<table')
        ->and($indexContent)->toContain('</table>')
        ->and($indexContent)->toContain('<thead')
        ->and($indexContent)->toContain('<tbody')
        ->and($indexContent)->toContain('{foreach')
        ->and($indexContent)->toContain('$authors->items')
        ->and($indexContent)->toContain('$author->name')
        ->and($indexContent)->toContain('$author->email')
        ->and($indexContent)->toContain('/admin/blog/authors/create');

    // Create has form with author fields
    expect($createContent)->toContain('<form')
        ->and($createContent)->toContain('method="post"')
        ->and($createContent)->toContain('name="name"')
        ->and($createContent)->toContain('name="email"')
        ->and($createContent)->toContain('name="bio"')
        ->and($createContent)->toContain('<label')
        ->and($createContent)->toContain('<button');

    // Edit has form pre-populated with author data
    expect($editContent)->toContain('<form')
        ->and($editContent)->toContain('$author->name')
        ->and($editContent)->toContain('$author->email')
        ->and($editContent)->toContain('$author->bio')
        ->and($editContent)->toContain('$author->id')
        ->and($editContent)->toContain('<button');
});

// Category templates

it('creates category index, create, and edit templates with parent dropdown', function () use ($viewsPath): void {
    $indexPath = $viewsPath . '/admin/category/index.latte';
    $createPath = $viewsPath . '/admin/category/create.latte';
    $editPath = $viewsPath . '/admin/category/edit.latte';

    expect(file_exists($indexPath))->toBeTrue('Category index template should exist')
        ->and(file_exists($createPath))->toBeTrue('Category create template should exist')
        ->and(file_exists($editPath))->toBeTrue('Category edit template should exist');

    $indexContent = file_get_contents($indexPath);
    $createContent = file_get_contents($createPath);
    $editContent = file_get_contents($editPath);

    // Index has table with category fields
    expect($indexContent)->toContain('<table')
        ->and($indexContent)->toContain('{foreach')
        ->and($indexContent)->toContain('$categories->items')
        ->and($indexContent)->toContain('$category->name')
        ->and($indexContent)->toContain('/admin/blog/categories/create');

    // Create has form with parent category dropdown
    expect($createContent)->toContain('<form')
        ->and($createContent)->toContain('method="post"')
        ->and($createContent)->toContain('name="name"')
        ->and($createContent)->toContain('name="parent_id"')
        ->and($createContent)->toContain('<select')
        ->and($createContent)->toContain('{foreach $categories as $cat}')
        ->and($createContent)->toContain('$cat->getId()')
        ->and($createContent)->toContain('$cat->getName()');

    // Edit has form with parent category dropdown pre-selected
    expect($editContent)->toContain('<form')
        ->and($editContent)->toContain('$category->name')
        ->and($editContent)->toContain('$category->id')
        ->and($editContent)->toContain('name="parent_id"')
        ->and($editContent)->toContain('<select')
        ->and($editContent)->toContain('{foreach $categories as $cat}');
});

it('shows category hierarchy with indentation in category list', function () use ($viewsPath): void {
    $content = file_get_contents($viewsPath . '/admin/category/index.latte');

    // Category list should show hierarchy with indentation based on depth
    expect($content)->toContain('$category->depth')
        ->and($content)->toContain('padding-left');
});

// Tag templates

it('creates tag index, create, and edit templates', function () use ($viewsPath): void {
    $indexPath = $viewsPath . '/admin/tag/index.latte';
    $createPath = $viewsPath . '/admin/tag/create.latte';
    $editPath = $viewsPath . '/admin/tag/edit.latte';

    expect(file_exists($indexPath))->toBeTrue('Tag index template should exist')
        ->and(file_exists($createPath))->toBeTrue('Tag create template should exist')
        ->and(file_exists($editPath))->toBeTrue('Tag edit template should exist');

    $indexContent = file_get_contents($indexPath);
    $createContent = file_get_contents($createPath);
    $editContent = file_get_contents($editPath);

    // Index has table with tag fields
    expect($indexContent)->toContain('<table')
        ->and($indexContent)->toContain('{foreach')
        ->and($indexContent)->toContain('$tags->items')
        ->and($indexContent)->toContain('$tag->name')
        ->and($indexContent)->toContain('/admin/blog/tags/create');

    // Create has form with tag fields
    expect($createContent)->toContain('<form')
        ->and($createContent)->toContain('method="post"')
        ->and($createContent)->toContain('name="name"')
        ->and($createContent)->toContain('<label')
        ->and($createContent)->toContain('<button');

    // Edit has form pre-populated with tag data
    expect($editContent)->toContain('<form')
        ->and($editContent)->toContain('$tag->name')
        ->and($editContent)->toContain('$tag->id')
        ->and($editContent)->toContain('<button');
});

// Comment templates

it('creates comment index and show templates', function () use ($viewsPath): void {
    $indexPath = $viewsPath . '/admin/comment/index.latte';
    $showPath = $viewsPath . '/admin/comment/show.latte';

    expect(file_exists($indexPath))->toBeTrue('Comment index template should exist')
        ->and(file_exists($showPath))->toBeTrue('Comment show template should exist');

    $indexContent = file_get_contents($indexPath);
    $showContent = file_get_contents($showPath);

    // Index has table with comment fields
    expect($indexContent)->toContain('<table')
        ->and($indexContent)->toContain('{foreach')
        ->and($indexContent)->toContain('$comments->items')
        ->and($indexContent)->toContain('$comment->name')
        ->and($indexContent)->toContain('$comment->email')
        ->and($indexContent)->toContain('$comment->postId')
        ->and($indexContent)->toContain('$comment->status');

    // Show has full comment details
    expect($showContent)->toContain('$comment->name')
        ->and($showContent)->toContain('$comment->email')
        ->and($showContent)->toContain('$comment->content')
        ->and($showContent)->toContain('$comment->status')
        ->and($showContent)->toContain('$comment->createdAt');
});

it('includes verify and delete actions on comment show template', function () use ($viewsPath): void {
    $content = file_get_contents($viewsPath . '/admin/comment/show.latte');

    expect($content)->toContain('verify')
        ->and($content)->toContain('Delete')
        ->and($content)->toContain('method="post"')
        ->and($content)->toContain('_method')
        ->and($content)->toContain('DELETE');
});

it('shows comment status badge on comment list', function () use ($viewsPath): void {
    $content = file_get_contents($viewsPath . '/admin/comment/index.latte');

    expect($content)->toContain('status-badge')
        ->and($content)->toContain('$comment->status');
});

// Layout and common patterns

it('extends admin-panel base layout for all templates', function () use ($viewsPath): void {
    $templates = [
        '/admin/author/index.latte',
        '/admin/author/create.latte',
        '/admin/author/edit.latte',
        '/admin/category/index.latte',
        '/admin/category/create.latte',
        '/admin/category/edit.latte',
        '/admin/tag/index.latte',
        '/admin/tag/create.latte',
        '/admin/tag/edit.latte',
        '/admin/comment/index.latte',
        '/admin/comment/show.latte',
    ];

    foreach ($templates as $template) {
        $content = file_get_contents($viewsPath . $template);

        expect($content)->toContain("{layout 'admin-panel::layout/base'}")
            ->and($content)->toContain('{block content}');
    }
});

it('includes pagination on all list templates', function () use ($viewsPath): void {
    $listTemplates = [
        '/admin/author/index.latte' => 'authors',
        '/admin/category/index.latte' => 'categories',
        '/admin/tag/index.latte' => 'tags',
        '/admin/comment/index.latte' => 'comments',
    ];

    foreach ($listTemplates as $template => $var) {
        $content = file_get_contents($viewsPath . $template);

        expect($content)->toContain('pagination')
            ->and($content)->toContain('$' . $var . '->shouldShowPagination()');
    }
});

it('includes flash message display on all templates', function () use ($viewsPath): void {
    $templates = [
        '/admin/author/index.latte' => false,
        '/admin/author/create.latte' => true,
        '/admin/author/edit.latte' => true,
        '/admin/category/index.latte' => false,
        '/admin/category/create.latte' => true,
        '/admin/category/edit.latte' => true,
        '/admin/tag/index.latte' => false,
        '/admin/tag/create.latte' => true,
        '/admin/tag/edit.latte' => true,
        '/admin/comment/index.latte' => false,
        '/admin/comment/show.latte' => false,
    ];

    foreach ($templates as $template => $hasErrors) {
        $content = file_get_contents($viewsPath . $template);

        // All templates extend base layout which includes flash messages
        expect($content)->toContain("{layout 'admin-panel::layout/base'}");

        // Form templates also display validation errors inline
        if ($hasErrors) {
            expect($content)->toContain('$errors');
        }
    }
});
