<?php

declare(strict_types=1);

it('all 5 .latte template files exist in packages/admin-panel-latte/resources/views/', function (): void {
    $viewsPath = dirname(__DIR__) . '/resources/views';

    expect(file_exists($viewsPath . '/auth/login.latte'))->toBeTrue('auth/login.latte should exist')
        ->and(file_exists($viewsPath . '/layout/base.latte'))->toBeTrue('layout/base.latte should exist')
        ->and(file_exists($viewsPath . '/dashboard/index.latte'))->toBeTrue('dashboard/index.latte should exist')
        ->and(file_exists($viewsPath . '/partials/sidebar.latte'))->toBeTrue('partials/sidebar.latte should exist')
        ->and(file_exists($viewsPath . '/partials/flash.latte'))->toBeTrue('partials/flash.latte should exist');
});

it('no .latte files remain in packages/admin-panel/resources/views/', function (): void {
    $adminPanelViewsPath = dirname(__DIR__, 2) . '/admin-panel/resources/views';

    $latteFiles = glob($adminPanelViewsPath . '/**/*.latte', GLOB_NOSORT);
    $latteFiles = $latteFiles ?: [];

    expect($latteFiles)->toBeEmpty('No .latte files should remain in admin-panel/resources/views/');
});

it('LayoutTemplateTest.php exists in packages/admin-panel-latte/tests/', function (): void {
    $testPath = dirname(__DIR__) . '/tests/LayoutTemplateTest.php';

    expect(file_exists($testPath))->toBeTrue('LayoutTemplateTest.php should exist in admin-panel-latte/tests/');
});

it('LayoutTemplateTest.php has been removed from packages/admin-panel/tests/Unit/Template/', function (): void {
    $oldTestPath = dirname(__DIR__, 2) . '/admin-panel/tests/Unit/Template/LayoutTemplateTest.php';

    expect(file_exists($oldTestPath))->toBeFalse(
        'LayoutTemplateTest.php should not exist in admin-panel/tests/Unit/Template/',
    );
});

it('the moved Pest file uses dirname(__DIR__) for $viewsPath (one level up)', function (): void {
    $testPath = dirname(__DIR__) . '/tests/LayoutTemplateTest.php';

    expect(file_exists($testPath))->toBeTrue();

    $content = file_get_contents($testPath);

    expect($content)->toContain("dirname(__DIR__) . '/resources/views'")
        ->and($content)->not->toContain('dirname(__DIR__, 3)');
});

it('the .gitkeep file in resources/views/ is removed once real templates land', function (): void {
    $gitkeepPath = dirname(__DIR__) . '/resources/views/.gitkeep';

    expect(file_exists($gitkeepPath))->toBeFalse('.gitkeep should be removed once real templates are present');
});
