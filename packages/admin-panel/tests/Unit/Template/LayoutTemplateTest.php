<?php

declare(strict_types=1);

$viewsPath = dirname(__DIR__, 3) . '/resources/views';

it('creates base layout template with html shell, sidebar, and content block', function () use ($viewsPath): void {
    $templatePath = $viewsPath . '/layout/base.latte';

    expect(file_exists($templatePath))->toBeTrue('Base layout template should exist');

    $content = file_get_contents($templatePath);

    expect($content)->toContain('<!DOCTYPE html>')
        ->and($content)->toContain('<html')
        ->and($content)->toContain('<head>')
        ->and($content)->toContain('</head>')
        ->and($content)->toContain('<body')
        ->and($content)->toContain('</body>')
        ->and($content)->toContain('</html>')
        ->and($content)->toContain('{include')
        ->and($content)->toContain('sidebar')
        ->and($content)->toContain('{block content}');
});

it('creates login template with email and password form fields', function () use ($viewsPath): void {
    $templatePath = $viewsPath . '/auth/login.latte';

    expect(file_exists($templatePath))->toBeTrue('Login template should exist');

    $content = file_get_contents($templatePath);

    expect($content)->toContain('<form')
        ->and($content)->toContain('method="post"')
        ->and($content)->toContain('type="email"')
        ->and($content)->toContain('name="email"')
        ->and($content)->toContain('type="password"')
        ->and($content)->toContain('name="password"')
        ->and($content)->toContain('<label')
        ->and($content)->toContain('<button');
});

it('creates dashboard template extending base layout', function () use ($viewsPath): void {
    $templatePath = $viewsPath . '/dashboard/index.latte';

    expect(file_exists($templatePath))->toBeTrue('Dashboard template should exist');

    $content = file_get_contents($templatePath);

    expect($content)->toContain('{layout')
        ->and($content)->toContain('layout/base')
        ->and($content)->toContain('{block content}')
        ->and($content)->toContain('Dashboard')
        ->and($content)->toContain('sections');
});

it('creates sidebar partial with menu items loop', function () use ($viewsPath): void {
    $templatePath = $viewsPath . '/partials/sidebar.latte';

    expect(file_exists($templatePath))->toBeTrue('Sidebar partial should exist');

    $content = file_get_contents($templatePath);

    expect($content)->toContain('<nav')
        ->and($content)->toContain('{foreach')
        ->and($content)->toContain('$menuItems')
        ->and($content)->toContain('getLabel()')
        ->and($content)->toContain('getUrl()')
        ->and($content)->toContain('<a');
});

it('creates flash message partial for success and error messages', function () use ($viewsPath): void {
    $templatePath = $viewsPath . '/partials/flash.latte';

    expect(file_exists($templatePath))->toBeTrue('Flash message partial should exist');

    $content = file_get_contents($templatePath);

    expect($content)->toContain('$flashMessages')
        ->and($content)->toContain('success')
        ->and($content)->toContain('error')
        ->and($content)->toContain('role="alert"');
});

it('includes csrf-safe form structure in login template', function () use ($viewsPath): void {
    $templatePath = $viewsPath . '/auth/login.latte';
    $content = file_get_contents($templatePath);

    expect($content)->toContain('type="hidden"')
        ->and($content)->toContain('name="_token"')
        ->and($content)->toContain('$csrfToken');
});

it('has content block that child templates can override', function () use ($viewsPath): void {
    $baseContent = file_get_contents($viewsPath . '/layout/base.latte');
    $dashboardContent = file_get_contents($viewsPath . '/dashboard/index.latte');

    expect($baseContent)->toContain('{block content}{/block}')
        ->and($dashboardContent)->toContain('{block content}')
        ->and($dashboardContent)->toContain('{/block}')
        ->and($dashboardContent)->toContain('{layout');
});
