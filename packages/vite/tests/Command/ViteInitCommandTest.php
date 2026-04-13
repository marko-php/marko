<?php

declare(strict_types=1);

use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Vite\ComposerPackageInstaller;
use Marko\TailwindCss\TailwindPublisher;
use Marko\TailwindCss\TailwindViteConfigUpdater;
use Marko\Vite\Command\ViteInitCommand;
use Marko\Vite\PackageJsonUpdater;
use Marko\Vite\ProjectFilePublisher;
use Marko\Vite\ScaffoldTemplateRenderer;
use Marko\Vite\ValueObjects\FilePublishResult;
use Marko\Vite\ViteInitPrompter;
use Marko\Vite\ViteInitSelections;
use Marko\Vite\ValueObjects\ViteConfig;
use Marko\Vite\VitePublisher;

class FakeViteInitPrompter extends ViteInitPrompter
{
    public function __construct(
        private readonly ViteInitSelections $selections = new ViteInitSelections(null, false),
    ) {}

    public function resolve(
        Input $input,
        Output $output,
    ): ViteInitSelections
    {
        return $this->selections;
    }
}

class FakeComposerPackageInstaller extends ComposerPackageInstaller
{
    public function __construct(
        private readonly array $missing = [],
        private readonly int $installStatus = 0,
    ) {
        parent::__construct(new ProjectPaths(sys_get_temp_dir()));
    }

    /** @var list<string> */
    public array $requested = [];

    public function missingPackages(array $packages): array
    {
        $this->requested = array_values(array_unique($packages));

        return array_values(array_intersect($this->requested, $this->missing));
    }

    public function ensureInstalled(
        array $packages,
        bool $dryRun,
        Output $output,
    ): int
    {
        $missing = $this->missingPackages($packages);

        foreach ($missing as $package) {
            $output->writeLine($dryRun
                ? sprintf('Would install Composer package `%s`', $package)
                : sprintf('Installing Composer package `%s`', $package));
        }

        return $this->installStatus;
    }
}

class FakeInertiaReactPublisher
{
    public function __construct(
        private readonly ProjectFilePublisher $publisher,
    ) {}

    public function publishJsEntrypoint(
        bool $force = false,
        bool $dryRun = false,
    ): FilePublishResult {
        return $this->publisher->publish(
            'resources/js/app.ts',
            "import { bootstrapMarkoInertiaReact } from \"../../vendor/marko/inertia-react/resources/js/bootstrap\";\n\nbootstrapMarkoInertiaReact();\n",
            $force,
            $dryRun,
        );
    }
}

class FakeInertiaReactViteConfigUpdater
{
    public function __construct(
        private readonly ProjectFilePublisher $publisher,
    ) {}

    public function ensureReactConfig(
        bool $force = false,
        bool $dryRun = false,
    ): FilePublishResult {
        return $this->publisher->publish(
            'vite.config.ts',
            "import { defineConfig } from 'vite';\n"
            . "import react from '@vitejs/plugin-react';\n"
            . "import { createBaseConfig } from './vendor/marko/vite/resources/config/createViteConfig';\n\n"
            . "export default defineConfig(\n"
            . "  createBaseConfig({\n"
            . "    plugins: [react()],\n"
            . "    entrypoints: ['resources/js/app.ts'],\n"
            . "  }),\n"
            . ");\n",
            true,
            $dryRun,
        );
    }
}

class BrokenTailwindViteConfigUpdater
{
    public function ensureTailwindConfig(
        bool $force = false,
        bool $dryRun = false,
    ): FilePublishResult {
        throw new RuntimeException('boom');
    }
}

beforeEach(function (): void {
    $this->tempDirectory = sys_get_temp_dir() . '/marko-vite-init-command-' . bin2hex(random_bytes(6));
    mkdir($this->tempDirectory, 0777, true);
});

afterEach(function (): void {
    if (!isset($this->tempDirectory) || !is_dir($this->tempDirectory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->tempDirectory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
            continue;
        }

        unlink($item->getPathname());
    }

    rmdir($this->tempDirectory);
});

function makeViteInitCommand(
    string $directory,
    ?ViteInitPrompter $prompter = null,
    ?ComposerPackageInstaller $installer = null,
    ?ViteConfig $viteConfig = null,
): ViteInitCommand
{
    $paths = new ProjectPaths($directory);
    $viteConfig ??= new ViteConfig(
        devServerUrl: 'http://localhost:5173',
        devProcessFilePath: $directory . '/.marko/dev.json',
        hotFilePath: $directory . '/public/hot',
        manifestPath: $directory . '/public/build/manifest.json',
        buildDirectory: '/build',
        assetsBaseUrl: '',
        defaultEntrypoints: [],
        rootEntrypointPath: 'resources/js/app.ts',
        rootViteConfigPath: 'vite.config.ts',
    );
    $projectPublisher = new ProjectFilePublisher($paths);
    $renderer = new ScaffoldTemplateRenderer($viteConfig);
    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(ProjectFilePublisher::class, $projectPublisher);
    $container->instance(
        'Marko\\TailwindCss\\Contracts\\TailwindPublisherInterface',
        new TailwindPublisher(
            new Marko\TailwindCss\DefaultTailwindEntrypointProvider(
                new Marko\Config\ConfigRepository([
                    'tailwindcss' => [
                        'enabled' => true,
                        'entrypoints' => [
                            'css' => 'resources/css/app.css',
                        ],
                    ],
                ]),
            ),
            $projectPublisher,
        ),
    );
    $container->instance(
        TailwindViteConfigUpdater::class,
        new TailwindViteConfigUpdater(
            $viteConfig,
            $paths,
            $projectPublisher,
            new Marko\TailwindCss\DefaultTailwindEntrypointProvider(
                new Marko\Config\ConfigRepository([
                    'tailwindcss' => [
                        'enabled' => true,
                        'entrypoints' => [
                            'css' => 'resources/css/app.css',
                        ],
                    ],
                ]),
            ),
            $renderer,
        ),
    );
    $container->bind(
        'Marko\\Inertia\\React\\Contracts\\InertiaReactPublisherInterface',
        FakeInertiaReactPublisher::class,
    );
    $container->bind(
        'Marko\\Inertia\\React\\InertiaReactViteConfigUpdater',
        FakeInertiaReactViteConfigUpdater::class,
    );

    return new ViteInitCommand(
        new PackageJsonUpdater($paths),
        new VitePublisher($viteConfig, $projectPublisher, $renderer),
        $container,
        $prompter ?? new FakeViteInitPrompter(),
        $installer ?? new FakeComposerPackageInstaller(),
        $viteConfig,
    );
}

function captureViteInitOutput(ViteInitCommand $command, array $argv): array
{
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $status = $command->execute(new Input($argv), $output);
    rewind($stream);
    $contents = stream_get_contents($stream);
    fclose($stream);

    return [$status, $contents];
}

test('vite init dry run reports planned changes without writing files', function (): void {
    [$status, $output] = captureViteInitOutput(
        makeViteInitCommand($this->tempDirectory),
        ['marko', 'vite:init', '--dry-run'],
    );

    expect($status)->toBe(0)
        ->and($output)->toContain('Would create package.json')
        ->and($output)->toContain('Added field `type`')
        ->and($output)->toContain('Added script `dev`')
        ->and($output)->toContain('Would publish vite.config.ts')
        ->and($output)->toContain('Would publish resources/js/app.ts');

    expect($this->tempDirectory . '/package.json')->not->toBeFile()
        ->and($this->tempDirectory . '/vite.config.ts')->not->toBeFile()
        ->and($this->tempDirectory . '/resources/js/app.ts')->not->toBeFile();
})->group('vite');

test('vite init can scaffold inertia react with tailwind in one command', function (): void {
    $prompter = new FakeViteInitPrompter(new ViteInitSelections('react', true));
    $installer = new FakeComposerPackageInstaller();

    [$status, $output] = captureViteInitOutput(
        makeViteInitCommand($this->tempDirectory, $prompter, $installer),
        ['marko', 'vite:init'],
    );

    $packageJson = json_decode(
        (string) file_get_contents($this->tempDirectory . '/package.json'),
        true,
        flags: JSON_THROW_ON_ERROR
    );
    $viteConfig = (string) file_get_contents($this->tempDirectory . '/vite.config.ts');
    $entrypoint = (string) file_get_contents($this->tempDirectory . '/resources/js/app.ts');
    $cssEntrypoint = (string) file_get_contents($this->tempDirectory . '/resources/css/app.css');

    expect($status)->toBe(0)
        ->and($installer->requested)->toBe(['marko/inertia-react', 'marko/tailwindcss'])
        ->and($output)->toContain('Published vite.config.ts (React-aware Vite config)')
        ->and($output)->toContain('Published resources/js/app.ts (Inertia React entrypoint)')
        ->and($output)->toContain('Updated vite.config.ts (Tailwind-aware Vite config)')
        ->and($output)->toContain('Published resources/css/app.css (Tailwind CSS entrypoint)')
        ->and($packageJson['devDependencies']['vite'])->toBe('latest')
        ->and($packageJson['devDependencies']['react'])->toBe('latest')
        ->and($packageJson['devDependencies']['react-dom'])->toBe('latest')
        ->and($packageJson['devDependencies']['@vitejs/plugin-react'])->toBe('latest')
        ->and($packageJson['devDependencies']['@inertiajs/react'])->toBe('latest')
        ->and($packageJson['devDependencies']['tailwindcss'])->toBe('latest')
        ->and($packageJson['devDependencies']['@tailwindcss/vite'])->toBe('latest')
        ->and($viteConfig)->toContain("import react from '@vitejs/plugin-react';")
        ->and($viteConfig)->toContain("import tailwindcss from '@tailwindcss/vite';")
        ->and($viteConfig)->toContain('plugins: [react(), tailwindcss()]')
        ->and($viteConfig)->toContain("entrypoints: ['resources/js/app.ts', 'resources/css/app.css']")
        ->and($entrypoint)->toContain('bootstrapMarkoInertiaReact')
        ->and($cssEntrypoint)->toContain('@import "tailwindcss";');
})->group('vite');

test('vite init honors custom configured root paths', function (): void {
    $viteConfig = new ViteConfig(
        devServerUrl: 'http://localhost:5173',
        devProcessFilePath: $this->tempDirectory . '/.marko/dev.json',
        hotFilePath: $this->tempDirectory . '/public/hot',
        manifestPath: $this->tempDirectory . '/public/build/manifest.json',
        buildDirectory: '/build',
        assetsBaseUrl: '',
        defaultEntrypoints: [],
        rootEntrypointPath: 'frontend/main.ts',
        rootViteConfigPath: 'config/vite.app.ts',
    );

    [$status] = captureViteInitOutput(
        makeViteInitCommand($this->tempDirectory, viteConfig: $viteConfig),
        ['marko', 'vite:init'],
    );

    $packageJson = json_decode(
        (string) file_get_contents($this->tempDirectory . '/package.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $viteConfigContents = (string) file_get_contents($this->tempDirectory . '/config/vite.app.ts');
    $entrypoint = (string) file_get_contents($this->tempDirectory . '/frontend/main.ts');

    expect($status)->toBe(0)
        ->and($packageJson['scripts']['dev'])->toBe('vite --config ./config/vite.app.ts')
        ->and($packageJson['scripts']['build'])->toBe('vite build --config ./config/vite.app.ts')
        ->and($viteConfigContents)->toContain("from '../vendor/marko/vite/resources/config/createViteConfig';")
        ->and($viteConfigContents)->toContain("entrypoints: ['frontend/main.ts']")
        ->and($entrypoint)->toContain("from '../vendor/marko/vite/resources/js/bootstrap';")
        ->and($entrypoint)->toContain("bootstrapMarkoVite('frontend/main.ts');");
})->group('vite');

test('vite init dry run reports package installs before addon commands when packages are missing', function (): void {
    $prompter = new FakeViteInitPrompter(new ViteInitSelections('react', true));
    $installer = new FakeComposerPackageInstaller(['marko/inertia-react', 'marko/tailwindcss']);

    [$status, $output] = captureViteInitOutput(
        makeViteInitCommand($this->tempDirectory, $prompter, $installer),
        ['marko', 'vite:init', '--dry-run'],
    );

    expect($status)->toBe(0)
        ->and($output)->toContain('Would install Composer package `marko/inertia-react`')
        ->and($output)->toContain('Would install Composer package `marko/tailwindcss`')
        ->and($output)->toContain(
            'Would continue `marko vite:init --inertia=react --tailwind --dry-run` after installing `marko/inertia-react`'
        );
})->group('vite');

test('vite init force replaces existing bootstrap files and updates package json', function (): void {
    file_put_contents($this->tempDirectory . '/package.json', json_encode([
        'private' => true,
        'type' => 'commonjs',
        'scripts' => [
            'dev' => 'custom-dev',
        ],
        'devDependencies' => [
            'vite' => '^5.0.0',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    file_put_contents($this->tempDirectory . '/vite.config.ts', 'custom config');
    mkdir($this->tempDirectory . '/resources/js', 0777, true);
    file_put_contents($this->tempDirectory . '/resources/js/app.ts', 'custom entrypoint');

    [$status, $output] = captureViteInitOutput(
        makeViteInitCommand($this->tempDirectory),
        ['marko', 'vite:init', '--force'],
    );

    $packageJson = json_decode(
        (string) file_get_contents($this->tempDirectory . '/package.json'),
        true,
        flags: JSON_THROW_ON_ERROR
    );

    expect($status)->toBe(0)
        ->and($output)->toContain('Updated field `type`')
        ->and($output)->toContain('Updated script `dev`')
        ->and($output)->toContain('Updated devDependency `vite`')
        ->and($output)->toContain('Replaced vite.config.ts')
        ->and($output)->toContain('Replaced resources/js/app.ts')
        ->and($packageJson['type'])->toBe('module')
        ->and($packageJson['scripts']['dev'])->toBe('vite --config ./vite.config.ts')
        ->and($packageJson['devDependencies']['vite'])->toBe('latest')
        ->and((string) file_get_contents($this->tempDirectory . '/vite.config.ts'))->toContain('createBaseConfig')
        ->and((string) file_get_contents($this->tempDirectory . '/resources/js/app.ts'))->toContain(
            'bootstrapMarkoVite'
        );
})->group('vite');

test('vite init rejects invalid inertia presets loudly', function (): void {
    $prompter = new ViteInitPrompter(fopen('php://memory', 'r'));

    expect(fn () => captureViteInitOutput(
        makeViteInitCommand($this->tempDirectory, $prompter),
        ['marko', 'vite:init', '--inertia=vu'],
    ))->toThrow(InvalidArgumentException::class, 'Unknown Inertia preset `vu`');
})->group('vite');

test(
    'vite init reports unexpected tailwind preset failures instead of calling the package missing',
    function (): void {
        $paths = new ProjectPaths($this->tempDirectory);
        $viteConfig = new ViteConfig(
            devServerUrl: 'http://localhost:5173',
            devProcessFilePath: $this->tempDirectory . '/.marko/dev.json',
            hotFilePath: $this->tempDirectory . '/public/hot',
            manifestPath: $this->tempDirectory . '/public/build/manifest.json',
            buildDirectory: '/build',
            assetsBaseUrl: '',
            defaultEntrypoints: [],
            rootEntrypointPath: 'resources/js/app.ts',
            rootViteConfigPath: 'vite.config.ts',
        );
        $projectPublisher = new ProjectFilePublisher($paths);
        $renderer = new ScaffoldTemplateRenderer($viteConfig);
        $container = new Container();
        $container->instance(ContainerInterface::class, $container);
        $container->instance(ProjectFilePublisher::class, $projectPublisher);
        $container->instance(
            'Marko\\TailwindCss\\Contracts\\TailwindPublisherInterface',
            new TailwindPublisher(
                new Marko\TailwindCss\DefaultTailwindEntrypointProvider(
                    new Marko\Config\ConfigRepository([
                        'tailwindcss' => [
                            'enabled' => true,
                            'entrypoints' => [
                                'css' => 'resources/css/app.css',
                            ],
                        ],
                    ]),
                ),
                $projectPublisher,
            ),
        );
        $container->instance(TailwindViteConfigUpdater::class, new BrokenTailwindViteConfigUpdater());
    
        $command = new ViteInitCommand(
            new PackageJsonUpdater($paths),
            new VitePublisher($viteConfig, $projectPublisher, $renderer),
            $container,
            new FakeViteInitPrompter(new ViteInitSelections(null, true)),
            new FakeComposerPackageInstaller(),
            $viteConfig,
        );
    
        [$status, $output] = captureViteInitOutput($command, ['marko', 'vite:init', '--tailwind']);
    
        expect($status)->toBe(1)
            ->and($output)->toContain('Tailwind preset failed: boom')
            ->and($output)->not->toContain('is not installed');
    }
)->group('vite');

test('vite init returns an error when publishing scaffold files fails', function (): void {
    file_put_contents($this->tempDirectory . '/resources', 'blocking file');

    [$status, $output] = captureViteInitOutput(
        makeViteInitCommand($this->tempDirectory),
        ['marko', 'vite:init'],
    );

    expect($status)->toBe(1)
        ->and($output)->toContain('Could not create directory')
        ->and($this->tempDirectory . '/package.json')->toBeFile();
})->group('vite');
