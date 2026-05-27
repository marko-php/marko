<?php

declare(strict_types=1);

describe('admin-panel-twig templates', function (): void {
    $viewsDir = dirname(__DIR__) . '/resources/views';

    test('auth/login.twig exists and renders a form with email and password fields', function () use ($viewsDir): void {
        $path = $viewsDir . '/auth/login.twig';

        expect(file_exists($path))->toBeTrue();

        $contents = file_get_contents($path);

        expect($contents)
            ->toContain('<form')
            ->and($contents)->toContain('name="email"')
            ->and($contents)->toContain('name="password"');
    });

    test('layout/base.twig exists and contains HTML doctype, sidebar include, and content block', function () use ($viewsDir): void {
        $path = $viewsDir . '/layout/base.twig';

        expect(file_exists($path))->toBeTrue();

        $contents = file_get_contents($path);

        expect($contents)
            ->toContain('<!DOCTYPE html>')
            ->and($contents)->toContain("{% include 'admin-panel::partials/sidebar'")
            ->and($contents)->toContain('{% block content %}');
    });

    test('dashboard/index.twig exists and extends layout/base.twig with a content block', function () use ($viewsDir): void {
        $path = $viewsDir . '/dashboard/index.twig';

        expect(file_exists($path))->toBeTrue();

        $contents = file_get_contents($path);

        expect($contents)
            ->toContain("{% extends 'admin-panel::layout/base' %}")
            ->and($contents)->toContain('{% block content %}');
    });

    test('partials/sidebar.twig exists and iterates menu items', function () use ($viewsDir): void {
        $path = $viewsDir . '/partials/sidebar.twig';

        expect(file_exists($path))->toBeTrue();

        $contents = file_get_contents($path);

        expect($contents)
            ->toContain('{% for item in menuItems %}')
            ->and($contents)->toContain('item.getUrl()')
            ->and($contents)->toContain('item.getLabel()');
    });

    test('partials/flash.twig exists and renders success and error message states', function () use ($viewsDir): void {
        $path = $viewsDir . '/partials/flash.twig';

        expect(file_exists($path))->toBeTrue();

        $contents = file_get_contents($path);

        expect($contents)
            ->toContain('flash-success')
            ->and($contents)->toContain('flash-error');
    });

    test('login.twig includes a CSRF hidden input with name _token', function () use ($viewsDir): void {
        $path = $viewsDir . '/auth/login.twig';

        expect(file_exists($path))->toBeTrue();

        $contents = file_get_contents($path);

        expect($contents)
            ->toContain('type="hidden"')
            ->and($contents)->toContain('name="_token"')
            ->and($contents)->toContain('{{ csrfToken }}');
    });

    test('the layout\'s content block can be overridden by child templates (verified via dashboard.twig)', function () use ($viewsDir): void {
        $layoutPath  = $viewsDir . '/layout/base.twig';
        $dashboardPath = $viewsDir . '/dashboard/index.twig';

        expect(file_exists($layoutPath))->toBeTrue()
            ->and(file_exists($dashboardPath))->toBeTrue();

        $layoutContents    = file_get_contents($layoutPath);
        $dashboardContents = file_get_contents($dashboardPath);

        expect($layoutContents)->toContain('{% block content %}')
            ->and($dashboardContents)->toContain('{% block content %}')
            ->and($dashboardContents)->toContain('{% endblock %}');
    });
});
