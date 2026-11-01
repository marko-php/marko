<?php

declare(strict_types=1);

use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\View\ModuleTemplateResolver;
use Marko\View\ViewConfig;

/**
 * A module directory holding the given files under resources/views/.
 *
 * @param list<string> $files Paths relative to resources/views/
 * @param array<string, mixed> $extra composer.json extra
 */
function templateListingModule(
    string $name,
    array $files,
    array $extra = [],
): ModuleManifest {
    $path = sys_get_temp_dir() . '/marko-template-all-' . bin2hex(random_bytes(8));
    mkdir($path . '/resources/views', 0755, true);

    foreach ($files as $file) {
        $absolute = $path . '/resources/views/' . $file;
        is_dir(dirname($absolute)) || mkdir(dirname($absolute), 0755, true);
        file_put_contents($absolute, 'x');
    }

    return new ModuleManifest(name: $name, version: '1.0.0', path: $path, extra: $extra);
}

function templateListingResolver(
    ModuleManifest ...$modules,
): ModuleTemplateResolver {
    return new ModuleTemplateResolver(
        new ModuleRepository($modules),
        new ViewConfig(new FakeConfigRepository(['view.extension' => '.latte'])),
    );
}

describe('ModuleTemplateResolver::all()', function (): void {
    it('lists every template of every module as module::path', function (): void {
        $blog = templateListingModule('vendor/blog', ['post/show.latte', 'post/list/item.latte', 'layout.latte']);
        $shop = templateListingModule('acme/shop', ['cart.latte']);

        expect(templateListingResolver($blog, $shop)->all())->toBe([
            'blog::layout',
            'blog::post/list/item',
            'blog::post/show',
            'shop::cart',
        ]);
    });

    it('lists only files with the configured extension', function (): void {
        $blog = templateListingModule('vendor/blog', ['show.latte', 'show.twig', 'README.md']);

        expect(templateListingResolver($blog)->all())->toBe(['blog::show']);
    });

    it('lists a template once when a templates_for module overrides it', function (): void {
        $blog = templateListingModule('vendor/blog', ['post/show.latte']);
        $theme = templateListingModule(
            'acme/blog-theme',
            ['post/show.latte', 'post/extra.latte'],
            ['marko' => ['templates_for' => 'vendor/blog']],
        );

        expect(templateListingResolver($blog, $theme)->all())->toBe([
            'blog::post/show',
            'blog::post/extra',
        ]);
    });

    it('lists only names that resolve', function (): void {
        $blog = templateListingModule('vendor/blog', ['ok.latte', 'has space.latte']);
        $resolver = templateListingResolver($blog);

        $names = $resolver->all();

        expect($names)->toBe(['blog::ok'])
            ->and($resolver->resolve($names[0]))->toBe($blog->path . '/resources/views/ok.latte');
    });

    it('returns an empty list when no module has views', function (): void {
        $module = new ModuleManifest(
            name: 'vendor/empty',
            version: '1.0.0',
            path: sys_get_temp_dir() . '/marko-template-none-' . bin2hex(random_bytes(4)),
        );

        expect(templateListingResolver($module)->all())->toBe([]);
    });
});
