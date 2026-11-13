<?php

declare(strict_types=1);

use Marko\Core\Path\ProjectPaths;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\View\CacheDirectoryGuard;
use Marko\View\Slot;
use Marko\View\Twig\SlotExtension;
use Marko\View\Twig\Tests\Fixtures\PathTemplateResolver;
use Marko\View\Twig\Tests\Fixtures\ViewProviders;
use Marko\View\Twig\TwigEngineFactory;
use Marko\View\Twig\TwigViewConfig;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @param array<string, string> $templates
 */
function makeSlotTwig(array $templates, bool $strictVariables = false): Environment
{
    $environment = new Environment(new ArrayLoader($templates), [
        'autoescape' => 'html',
        'strict_variables' => $strictVariables,
    ]);
    $environment->addExtension(new SlotExtension());

    return $environment;
}

describe('SlotExtension', function (): void {
    test('it registers a slot function', function (): void {
        $names = array_map(
            fn ($function): string => $function->getName(),
            (new SlotExtension())->getFunctions(),
        );

        expect($names)->toBe(['slot']);
    });

    test('it outputs the pre-rendered HTML of a named slot without escaping it', function (): void {
        $twig = makeSlotTwig(['layout' => '<main>{{ slot(\'content\') }}</main>']);

        $html = $twig->render('layout', ['slots' => ['content' => '<h1>Hello World</h1>']]);

        expect($html)->toBe('<main><h1>Hello World</h1></main>');
    });

    test('it outputs an empty string when a slot has no content', function (): void {
        $twig = makeSlotTwig(['layout' => '<aside>{{ slot(\'sidebar\') }}</aside>']);

        expect($twig->render('layout', ['slots' => []]))->toBe('<aside></aside>');
    });

    test('it handles nested slot names with dot-notation', function (): void {
        $twig = makeSlotTwig(['layout' => '{{ slot(\'header.title\') }}']);

        $html = $twig->render('layout', ['slots' => ['header.title' => '<h1>Nested Title</h1>']]);

        expect($html)->toBe('<h1>Nested Title</h1>');
    });

    test('it works with multiple slots in the same template', function (): void {
        $twig = makeSlotTwig([
            'layout' => '<header>{{ slot(\'header\') }}</header><main>{{ slot(\'content\') }}</main>'
                . '<footer>{{ slot(\'footer\') }}</footer>',
        ]);

        $html = $twig->render('layout', [
            'slots' => [
                'header' => '<h1>Title</h1>',
                'content' => '<p>Body</p>',
                'footer' => '<p>Footer</p>',
            ],
        ]);

        expect($html)->toBe('<header><h1>Title</h1></header><main><p>Body</p></main><footer><p>Footer</p></footer>');
    });

    test('it prints the sub-slot placeholder when the template was given no slots', function (): void {
        $twig = makeSlotTwig(['tabs' => '<div>{{ slot(\'tab.details\') }}</div>'], strictVariables: true);

        expect($twig->render('tabs', []))->toBe('<div>' . Slot::placeholder('tab.details') . '</div>');
    });

    test('it is registered by TwigEngineFactory', function (): void {
        $cacheDir = sys_get_temp_dir() . '/twig-slot-factory-' . bin2hex(random_bytes(8));
        $factory = new TwigEngineFactory(
            new TwigViewConfig(new FakeConfigRepository([
                'view.cache_directory' => $cacheDir,
                'view.auto_refresh' => true,
                'view.strict_variables' => false,
                'view.autoescape' => 'html',
                'view.debug' => false,
                'view.charset' => 'UTF-8',
            ])),
            ViewProviders::extension(),
            new CacheDirectoryGuard(new ProjectPaths(sys_get_temp_dir())),
            new PathTemplateResolver(),
        );
        $environment = $factory->create();

        $html = $environment->createTemplate('{{ slot(\'content\') }}')
            ->render(['slots' => ['content' => '<p>Factory Slot</p>']]);

        expect($html)->toBe('<p>Factory Slot</p>');

        if (is_dir($cacheDir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($cacheDir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }

            rmdir($cacheDir);
        }
    });
});
