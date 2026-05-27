<?php

declare(strict_types=1);

use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\View\ModuleTemplateResolver;
use Marko\View\ViewConfig;

it('ModuleTemplateResolver resolves admin-panel::dashboard/index to the new admin-panel-latte path', function (): void {
    $adminPanelLatteDir = dirname(__DIR__);

    $modules = [
        new ModuleManifest(
            name: 'marko/admin-panel-latte',
            version: '1.0.0',
            path: $adminPanelLatteDir,
            source: 'vendor',
            extra: ['marko' => ['templates_for' => 'marko/admin-panel']],
        ),
    ];

    $resolver = new ModuleTemplateResolver(
        new ModuleRepository($modules),
        new ViewConfig(new FakeConfigRepository(['view.extension' => '.latte'])),
    );

    $result = $resolver->resolve('admin-panel::dashboard/index');

    expect($result)->toBe($adminPanelLatteDir . '/resources/views/dashboard/index.latte');
});
